<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\BreakTime;
use Carbon\Carbon;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\AttendanceRequest as AttendanceRequest;

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

    // 勤怠修正・申請
    public function update(UpdateAttendanceRequest $request, $id)
    {

$query = Attendance::with('breakTimes');

if (auth()->check() && auth()->user()->role !== 'admin') {
    $query->where('user_id', auth()->id());
}

$attendance = $query->findOrFail($id);

        // 勤怠レコードを id ベースで取得（user_id も確認）
        $attendance = Attendance::with('breakTimes')
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        // 勤務日（hiddenで送信される）を取得
        $workDate = $request->input('work_date') ?? now()->toDateString();
        $attendance->work_date = $workDate;

        // 出勤・退勤・備考の更新
        $attendance->started_at = $request->input('started_at');
        $attendance->ended_at = $request->input('ended_at');
        $attendance->note = $request->input('note');
        $attendance->is_submitted = true;
        $attendance->save();

        // 休憩時間の更新・追加
        $submittedBreaks = $request->input('breaks', []);
        $existingBreaks = $attendance->breakTimes->sortBy('started_at')->values();
        $workDateFormatted = Carbon::parse($workDate)->format('Y-m-d');

        foreach ($submittedBreaks as $index => $breakData) {
            $startedAt = $breakData['started_at'] ?? null;
            $endedAt = $breakData['ended_at'] ?? null;

            // 両方空ならスキップ
            if (empty($startedAt) && empty($endedAt)) {
                continue;
            }

            // 日付と時刻を合成
            $startedAtFull = $startedAt ? Carbon::parse("{$workDateFormatted} {$startedAt}") : null;
            $endedAtFull = $endedAt ? Carbon::parse("{$workDateFormatted} {$endedAt}") : null;

            if (isset($existingBreaks[$index])) {
                // 既存休憩の更新
                $break = $existingBreaks[$index];
                $break->started_at = $startedAtFull;
                $break->ended_at = $endedAtFull;
                $break->save();
            } else {
                // 新規休憩の追加
                $attendance->breakTimes()->create([
                    'started_at' => $startedAtFull,
                    'ended_at' => $endedAtFull,
                ]);
            }
        }

        // 勤怠修正申請の登録
        AttendanceRequest::create([
            'attendance_id' => $attendance->id,
            'user_id' => auth()->id(),
            'target_date' => $workDate,
            'reason' => $attendance->note ?? '備考なし',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        // 修正後の詳細画面へ遷移
        return redirect()->route('attendance.submitted', ['id' => $attendance->id])
            ->with('success', '勤怠情報を修正・申請しました。');
    }

public function store(UpdateAttendanceRequest $request)
{
    $workDate = $request->input('work_date') ?? now()->toDateString();
    $workDateFormatted = Carbon::parse($workDate)->format('Y-m-d');

    // 勤怠レコードの作成
    $attendance = new Attendance();
    $attendance->user_id = auth()->id();
    $attendance->work_date = $workDate;
    $attendance->started_at = $request->input('started_at');
    $attendance->ended_at = $request->input('ended_at');
    $attendance->note = $request->input('note');
    $attendance->is_submitted = true;
    $attendance->save();

    // 休憩時間の保存
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

    // 勤怠修正申請の登録
    AttendanceRequest::create([
        'attendance_id' => $attendance->id,
        'user_id' => auth()->id(),
        'target_date' => $workDate,
        'reason' => $attendance->note ?? '備考なし',
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    // 修正申請画面へ遷移
    return redirect()->route('attendance.submitted', ['id' => $attendance->id])
        ->with('success', '勤怠情報を登録・申請しました。');
}


    public function submittedList($id)
    {
        $attendance = Attendance::with([
            'user',
            'breakTimes' => fn($q) => $q->orderBy('started_at')
        ])->findOrFail($id);

        return view('attendance.submitted', compact('attendance'));
    }
}