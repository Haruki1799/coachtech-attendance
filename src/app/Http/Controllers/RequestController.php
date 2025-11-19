<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceRequest as StampCorrectionRequest;
use Illuminate\Support\Facades\Auth;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');
        $user = Auth::user();

        $requests = StampCorrectionRequest::with('user')
            ->where('user_id', $user->id)
            ->when(in_array($status, ['pending', 'approved', 'rejected']), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('requested_at')
            ->get();

        return view('requestlist', [
            'requests' => $requests,
            'status' => $status,
        ]);
    }

    public function show($id)
    {
        $application = StampCorrectionRequest::with(['user', 'attendance.breakTimes'])->findOrFail($id);

        return view('attendance.submitted', [
            'attendance' => $application->attendance,
        ]);
    }
}
