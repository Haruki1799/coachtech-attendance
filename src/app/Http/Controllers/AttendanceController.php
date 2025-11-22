<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\AttendanceRequest as AttendanceRequest;

class AttendanceController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('work_date', $today)
            ->latest()
            ->first();

        $isWorking = $attendance && is_null($attendance->ended_at);
        $isOnBreak = $isWorking && $attendance->breakTimes()->whereNull('ended_at')->exists();
        $isClockedOut = $attendance && $attendance->ended_at !== null;

        return view('attendance', compact('isWorking', 'isOnBreak', 'isClockedOut'));
    }

    public function clockin()
    {
        $today = now()->toDateString();

        $alreadyClockedIn = Attendance::where('user_id', auth()->id())
            ->whereDate('work_date', $today)
            ->exists();

        if ($alreadyClockedIn) {
            return redirect()->route('attendance')->with('error', '本日はすでに出勤済です。');
        }

        Attendance::create([
            'user_id' => auth()->id(),
            'started_at' => now(),
            'work_date' => $today,
        ]);

        return redirect()->route('attendance');
    }

    public function clockout()
    {
        $attendance = Attendance::where('user_id', auth()->id())
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if ($attendance) {
            $attendance->update(['ended_at' => now()]);
        }

        return redirect()->route('attendance');
    }

    public function breakin()
    {
        $attendance = Attendance::where('user_id', auth()->id())
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if ($attendance) {
            $attendance->breakTimes()->create([
                'started_at' => now(),
            ]);
        }

        return redirect()->route('attendance');
    }

    public function breakout()
    {
        $attendance = Attendance::where('user_id', auth()->id())
            ->whereNull('ended_at')
            ->latest()
            ->first();

        if ($attendance) {
            $break = $attendance->breakTimes()->whereNull('ended_at')->latest()->first();
            if ($break) {
                $break->update(['ended_at' => now()]);
            }
        }

        return redirect()->route('attendance');
    }

    public function list(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);

        $currentMonth = Carbon::create($year, $month);
        $prevMonth = $currentMonth->copy()->subMonth();
        $nextMonth = $currentMonth->copy()->addMonth();

        $daysInMonth = collect();
        $start = $currentMonth->copy()->startOfMonth();
        $end = $currentMonth->copy()->endOfMonth();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $daysInMonth->push($date->copy());
        }

        $attendances = Attendance::with('breakTimes')
            ->where('user_id', auth()->id())
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->get()
            ->keyBy(fn($item) => $item->work_date->format('Y-m-d'));

        return view('attendance.list', compact('daysInMonth', 'attendances', 'year', 'month', 'currentMonth', 'prevMonth', 'nextMonth'));
    }

    public function show($id = null)
    {
        $attendance = null;

        if ($id) {
            $query = Attendance::with(['breakTimes' => fn($q) => $q->orderBy('started_at')]);

            if (auth()->user()->role !== 'admin') {
                $query->where('user_id', auth()->id());
            }

            $attendance = $query->find($id);
        }

        if (!$attendance) {
            $workDate = request()->input('date') ?? now()->toDateString();
            $attendance = new Attendance([
                'user_id' => auth()->id(),
                'work_date' => $workDate,
            ]);
            $attendance->setRelation('breakTimes', collect());
        }

        return view('attendance.detail', compact('attendance'));
    }

    public function update(UpdateAttendanceRequest $request, $id)
    {

        $query = Attendance::with('breakTimes');

        if (auth()->check() && auth()->user()->role !== 'admin') {
            $query->where('user_id', auth()->id());
        }

        $attendance = $query->findOrFail($id);

        $attendance = Attendance::with('breakTimes')
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $workDate = $request->input('work_date') ?? now()->toDateString();
        $attendance->work_date = $workDate;

        $attendance->started_at = $request->input('started_at');
        $attendance->ended_at = $request->input('ended_at');
        $attendance->note = $request->input('note');
        $attendance->is_submitted = true;
        $attendance->save();

        $submittedBreaks = $request->input('breaks', []);
        $existingBreaks = $attendance->breakTimes->sortBy('started_at')->values();
        $workDateFormatted = Carbon::parse($workDate)->format('Y-m-d');

        foreach ($submittedBreaks as $index => $breakData) {
            $startedAt = $breakData['started_at'] ?? null;
            $endedAt = $breakData['ended_at'] ?? null;

            if (empty($startedAt) && empty($endedAt)) {
                continue;
            }

            $startedAtFull = $startedAt ? Carbon::parse("{$workDateFormatted} {$startedAt}") : null;
            $endedAtFull = $endedAt ? Carbon::parse("{$workDateFormatted} {$endedAt}") : null;

            if (isset($existingBreaks[$index])) {
                $break = $existingBreaks[$index];
                $break->started_at = $startedAtFull;
                $break->ended_at = $endedAtFull;
                $break->save();
            } else {
                $attendance->breakTimes()->create([
                    'started_at' => $startedAtFull,
                    'ended_at' => $endedAtFull,
                ]);
            }
        }

        AttendanceRequest::create([
            'attendance_id' => $attendance->id,
            'user_id' => auth()->id(),
            'target_date' => $workDate,
            'reason' => $attendance->note ?? '備考なし',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return redirect()->route('attendance.submitted', ['id' => $attendance->id])
            ->with('success', '勤怠情報を修正・申請しました。');
    }

    public function store(UpdateAttendanceRequest $request)
    {
        $workDate = $request->input('work_date') ?? now()->toDateString();
        $workDateFormatted = Carbon::parse($workDate)->format('Y-m-d');

        $attendance = new Attendance();
        $attendance->user_id = auth()->id();
        $attendance->work_date = $workDate;
        $attendance->started_at = $request->input('started_at');
        $attendance->ended_at = $request->input('ended_at');
        $attendance->note = $request->input('note');
        $attendance->is_submitted = true;
        $attendance->save();

        $submittedBreaks = $request->input('breaks', []);
        foreach ($submittedBreaks as $breakData) {
            $startedAt = $breakData['started_at'] ?? null;
            $endedAt = $breakData['ended_at'] ?? null;

            if (empty($startedAt) && empty($endedAt)) {
                continue;
            }

            $startedAtFull = $startedAt ? Carbon::parse("{$workDateFormatted} {$startedAt}") : null;
            $endedAtFull = $endedAt ? Carbon::parse("{$workDateFormatted} {$endedAt}") : null;

            $attendance->breakTimes()->create([
                'started_at' => $startedAtFull,
                'ended_at' => $endedAtFull,
            ]);
        }

        AttendanceRequest::create([
            'attendance_id' => $attendance->id,
            'user_id' => auth()->id(),
            'target_date' => $workDate,
            'reason' => $attendance->note ?? '備考なし',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return redirect()->route('attendance.submitted', ['id' => $attendance->id])
            ->with('success', '勤怠情報を登録・申請しました。');
    }

    public function submittedList($attendanceId, $requestId = null)
    {
        $attendance = Attendance::with(['user', 'breakTimes', 'requests'])->findOrFail($attendanceId);

        $request = $requestId
            ? $attendance->requests()->where('id', $requestId)->first()
            : $attendance->requests()->latest('requested_at')->first();

        return view('attendance.submitted', compact('attendance', 'request'));
    }
}