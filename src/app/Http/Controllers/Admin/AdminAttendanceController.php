<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\User;
use App\Models\AttendanceRequest as AttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAttendanceController extends Controller
{
    public function adminDetail(Request $request, $id = null)
    {
        if ($id) {
            $attendance = Attendance::with(['user', 'breakTimes'])->find($id);
        } elseif ($request->filled(['user_id', 'date'])) {
            $attendance = Attendance::where('user_id', $request->user_id)
                ->whereDate('work_date', $request->date)
                ->with(['user', 'breakTimes'])
                ->first();
        }

        if (!$attendance) {
            if (!$request->user_id || !$request->date) {
                return redirect()->route('admin.list')->with('error', 'ユーザーIDまたは日付が指定されていません');
            }

            $attendance = new Attendance([
                'user_id' => $request->user_id,
                'work_date' => $request->date,
            ]);

            $attendance->setRelation('user', User::find($request->user_id));
            $attendance->setRelation('breakTimes', collect());
        }

        if ($attendance->is_submitted) {
            $requestModel = AttendanceRequest::where('attendance_id', $attendance->id)->first();

            if ($requestModel) {
                return view('attendance.submitted', [
                    'attendance' => $attendance,
                    'request' => $requestModel,
                ]);
            } else {
                return view('admin.admin_submitted', compact('attendance'));
            }
        }

        return view('admin.admin_detail', compact('attendance'));
    }

    public function update(UpdateAttendanceRequest $request, $id)
    {
        $attendance = Attendance::with('breakTimes')->findOrFail($id);
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

            if (empty($startedAt) && empty($endedAt)) continue;

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

        AttendanceRequest::updateOrCreate(
            ['attendance_id' => $attendance->id],
            [
                'user_id' => $attendance->user_id,
                'target_date' => $workDate,
                'reason' => $attendance->note ?? '備考なし',
                'status' => 'pending',
                'requested_at' => now(),
            ]
        );

        return redirect()->to("/admin/attendance/list?user_id={$attendance->user_id}&year=" . Carbon::parse($attendance->work_date)->year . "&month=" . Carbon::parse($attendance->work_date)->month)
            ->with('success', '勤怠情報を登録しました');
    }

    public function store(UpdateAttendanceRequest $request)
    {
        $userId = $request->input('user_id');
        if (!$userId || !User::find($userId)) {
            return redirect()->back()->with('error', '有効なユーザーIDが指定されていません');
        }

        $workDate = $request->input('work_date') ?? now()->toDateString();
        $workDateFormatted = Carbon::parse($workDate)->format('Y-m-d');

        $attendance = new Attendance();
        $attendance->user_id = $userId;
        $attendance->work_date = $workDate;
        $attendance->started_at = $request->input('started_at');
        $attendance->ended_at = $request->input('ended_at');
        $attendance->note = $request->input('note');
        $attendance->is_submitted = true;
        $attendance->save();

        foreach ($request->input('breaks', []) as $breakData) {
            $startedAt = $breakData['started_at'] ?? null;
            $endedAt = $breakData['ended_at'] ?? null;

            if (empty($startedAt) && empty($endedAt)) continue;

            $startedAtFull = $startedAt ? Carbon::parse("{$workDateFormatted} {$startedAt}") : null;
            $endedAtFull = $endedAt ? Carbon::parse("{$workDateFormatted} {$endedAt}") : null;

            $attendance->breakTimes()->create([
                'started_at' => $startedAtFull,
                'ended_at' => $endedAtFull,
            ]);
        }

        AttendanceRequest::updateOrCreate(
            ['attendance_id' => $attendance->id],
            [
                'user_id' => $attendance->user_id,
                'target_date' => $workDate,
                'reason' => $attendance->note ?? '備考なし',
                'status' => 'pending',
                'requested_at' => now(),
            ]
        );

        return redirect()->to("/admin/attendance/list?user_id={$attendance->user_id}&year=" . Carbon::parse($attendance->work_date)->year . "&month=" . Carbon::parse($attendance->work_date)->month)
            ->with('success', '勤怠情報を登録しました');
    }

    public function submitted($id)
    {
        $attendance = Attendance::with([
            'user',
            'breakTimes' => fn($q) => $q->orderBy('started_at')
        ])->findOrFail($id);

        $requestModel = \App\Models\AttendanceRequest::where('attendance_id', $attendance->id)->first();

        return view('admin.admin_submitted', [
            'attendance' => $attendance,
            'request' => $requestModel,
        ]);
    }

    public function list(Request $request)
    {
        $userId = $request->input('user_id');
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $user = User::findOrFail($userId);
        $currentMonth = Carbon::create($year, $month);
        $prevMonth = $currentMonth->copy()->subMonth();
        $nextMonth = $currentMonth->copy()->addMonth();

        $start = $currentMonth->copy()->startOfMonth();
        $end = $currentMonth->copy()->endOfMonth();

        $daysInMonth = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $daysInMonth->push($date->copy());
        }

        $attendances = Attendance::with('breakTimes')
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$start, $end])
            ->get()
            ->keyBy(fn($item) => $item->work_date->format('Y-m-d'));

        return view('admin.attendance_list', compact(
            'user',
            'daysInMonth',
            'attendances',
            'year',
            'month',
            'currentMonth',
            'prevMonth',
            'nextMonth'
        ));
    }

    public function monthlyList(Request $request)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $currentMonth = Carbon::create($year, $month);
        $start = $currentMonth->copy()->startOfMonth();
        $end = $currentMonth->copy()->endOfMonth();

        $attendances = Attendance::with('user')
            ->whereBetween('work_date', [$start, $end])
            ->get()
            ->groupBy('user_id');

        $users = User::whereIn('id', $attendances->keys())->get();

        return view('admin.monthly_summary', compact('users', 'attendances', 'year', 'month', 'currentMonth'));
    }

    public function exportMonthlyCsv(Request $request): StreamedResponse
    {
        $userId = $request->input('user_id');
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $user = User::findOrFail($userId);
        $currentMonth = Carbon::create($year, $month);
        $start = $currentMonth->copy()->startOfMonth();
        $end = $currentMonth->copy()->endOfMonth();

        $attendances = Attendance::with('breakTimes')
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$start, $end])
            ->orderBy('work_date')
            ->get();

        $filename = "{$user->name}_{$year}_{$month}_勤怠.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        return response()->streamDownload(function () use ($attendances) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['日付', '出勤', '退勤', '休憩時間', '合計時間', '備考']);

            foreach ($attendances as $attendance) {
                $breakMinutes = $attendance->breakTimes->sum(function ($break) {
                    $start = Carbon::make($break->started_at);
                    $end = Carbon::make($break->ended_at);
                    return ($start && $end) ? $end->diffInMinutes($start) : 0;
                });

                $start = Carbon::make($attendance->started_at);
                $end = Carbon::make($attendance->ended_at);
                $totalMinutes = ($start && $end) ? $end->diffInMinutes($start) - $breakMinutes : null;

                fputcsv($stream, [
                    $attendance->work_date->format('Y-m-d'),
                    $start?->format('H:i') ?? '',
                    $end?->format('H:i') ?? '',
                    sprintf('%d:%02d', intdiv($breakMinutes, 60), $breakMinutes % 60),
                    $totalMinutes !== null ? sprintf('%d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60) : '',
                    $attendance->note ?? '',
                ]);
            }

            fclose($stream);
        }, $filename, $headers);
    }
}
