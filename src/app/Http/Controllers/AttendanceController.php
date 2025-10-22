<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\BreakTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        // 今日の出勤記録を取得
        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('started_at', $today)
            ->latest()
            ->first();

        // 今日出勤していて、まだ退勤していないなら勤務中
        $isWorking = $attendance && is_null($attendance->ended_at);

        // 今日の勤務中で、休憩中かどうか
        $isOnBreak = $isWorking && $attendance->breakTimes()
            ->whereNull('ended_at')
            ->exists();

        // 今日の出勤記録があり、退勤済みなら退勤済
        $isClockedOut = $attendance && $attendance->ended_at !== null;

        return view('attendance', compact('isWorking', 'isOnBreak', 'isClockedOut'));
    }

    public function clockin(Request $request)
    {
        $today = now()->toDateString();

        // 今日すでに出勤済みかチェック
        $alreadyClockedIn = Attendance::where('user_id', auth()->id())
            ->whereDate('started_at', $today)
            ->exists();

        if ($alreadyClockedIn) {
            return redirect()->route('attendance')->with('error', '本日はすでに出勤済です。');
        }

        Attendance::create([
            'user_id' => auth()->id(),
            'started_at' => now(),
            'work_date' => now()->toDateString(),
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
            $break = $attendance->breakTimes()
                ->whereNull('ended_at')
                ->latest()
                ->first();

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

        // 月初〜月末の日付一覧を生成
        $daysInMonth = collect();
        $start = $currentMonth->copy()->startOfMonth();
        $end = $currentMonth->copy()->endOfMonth();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $daysInMonth->push($date->copy());
        }

        // 勤怠データを取得（キーを work_date に）
        $attendances = Attendance::with('breakTimes')
            ->where('user_id', auth()->id())
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->get()
            ->keyBy(function ($item) {
                return $item->work_date->format('Y-m-d');
            });

        return view('attendance.list', compact('daysInMonth', 'attendances', 'year', 'month', 'currentMonth', 'prevMonth', 'nextMonth'));
    }
}

