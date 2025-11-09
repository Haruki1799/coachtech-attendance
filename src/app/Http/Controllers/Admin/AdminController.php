<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;

class AdminController extends Controller
{
    public function list(Request $request)
    {
        $date = $request->input('date') ?? now()->toDateString();
        $targetDate = \Carbon\Carbon::parse($date);

        $users = User::where('role', 'user')->get();

        $attendances = Attendance::with('breakTimes')
            ->whereDate('work_date', $targetDate)
            ->get()
            ->keyBy('user_id');

        return view('admin.list', compact('users', 'attendances', 'targetDate'));
    }

    public function adminDetail(Request $request, $id = null)
    {
        if ($id) {
            $attendance = Attendance::with(['user', 'breakTimes'])->findOrFail($id);
        } else {
            $attendance = Attendance::where('user_id', $request->user_id)
                ->whereDate('work_date', $request->date)
                ->with(['user', 'breakTimes'])
                ->first();
        }

        // 勤怠申請の状態を確認
        $requestStatus = \App\Models\Request::where('attendance_id', $attendance->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($requestStatus) {
            return view('admin.admin_submitted', compact('attendance', 'requestStatus'));
        }

        return view('admin.admin_detail', compact('attendance'));
    }

    public function staffList()
    {
        $staffs = User::where('role', 'user')->orderBy('name')->get();

        return view('admin.staff_list', compact('staffs'));
    }

}
