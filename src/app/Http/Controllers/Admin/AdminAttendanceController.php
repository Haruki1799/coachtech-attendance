<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use App\Models\Request as AttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use Carbon\Carbon;

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
            return view('admin.admin_submitted', compact('attendance'));
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

        return redirect()->route('admin.attendance.admin_submitted', ['id' => $attendance->id])
            ->with('success', '勤怠情報を修正・申請しました');
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

        return redirect()->route('admin.attendance.admin_submitted', ['id' => $attendance->id])
            ->with('success', '勤怠情報を登録・申請しました');
    }

    public function submitted($id)
    {
        $attendance = Attendance::with([
            'user',
            'breakTimes' => fn($q) => $q->orderBy('started_at')
        ])->findOrFail($id);

        return view('admin.admin_submitted', compact('attendance'));
    }
}