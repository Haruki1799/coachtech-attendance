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

    public function detail(Request $request, $id = null)
    {
        if ($id) {
            $attendance = Attendance::with('breakTimes')->findOrFail($id);
        } else {
            $date = $request->input('date');
            $userId = $request->input('user_id');
            $attendance = Attendance::with('breakTimes')
                ->where('user_id', $userId)
                ->whereDate('work_date', $date)
                ->first();
        }

        return view('admin.detail', compact('attendance'));
    }
}
