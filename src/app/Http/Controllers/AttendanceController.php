<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\BreakTime;
use Carbon\Carbon;
use App\Http\Requests\UpdateAttendanceRequest;

class AttendanceController extends Controller
{
    // 出勤状況のトップ画面
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

    // 出勤打刻
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

    // 退勤打刻
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

    // 休憩開始
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

    // 休憩終了
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

    // 月別一覧表示
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

    // 詳細表示
    public function show($id = null)
    {
        $attendance = null;

        if ($id) {
            $attendance = Attendance::with(['breakTimes' => fn($q) => $q->orderBy('started_at')])
                ->where('user_id', auth()->id())
                ->find($id);
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

    // 勤怠修正
    public function update(UpdateAttendanceRequest $request, $id)
    {
        $attendance = Attendance::with('breakTimes')->findOrFail($id);

        $attendance->started_at = $request->input('started_at');
        $attendance->ended_at = $request->input('ended_at');
        $attendance->note = $request->input('note');
        $attendance->save();

        $submittedBreaks = $request->input('breaks', []);
        $existingBreaks = $attendance->breakTimes->sortBy('started_at')->values();

        foreach ($submittedBreaks as $index => $breakData) {
            $startedAt = $breakData['started_at'] ?? null;
            $endedAt = $breakData['ended_at'] ?? null;

            if (empty($startedAt) && empty($endedAt)) {
                continue;
            }

            if (isset($existingBreaks[$index])) {
                $break = $existingBreaks[$index];
                $break->started_at = $startedAt;
                $break->ended_at = $endedAt;
                $break->save();
            } else {
                $attendance->breakTimes()->create([
                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                ]);
            }
        }

        return redirect()->route('attendance.show', ['id' => $attendance->id])
            ->with('success', '勤怠情報を更新しました。');
    }
}