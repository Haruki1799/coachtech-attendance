<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Request as StampCorrectionRequest;

class AdminRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        $requests = StampCorrectionRequest::with('user')
            ->when(in_array($status, ['pending', 'approved', 'rejected']), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('requested_at')
            ->get();

        return view('admin.admin_requestlist', [
            'requests' => $requests,
            'status' => $status,
        ]);
    }

    public function show($id)
    {
        $application = StampCorrectionRequest::with(['user', 'attendance.breakTimes'])->findOrFail($id);

        return view('admin.admin_submitted', [
            'attendance' => $application->attendance,
            'request' => $application,
        ]);
    }

    public function approve($id)
    {
        $request = StampCorrectionRequest::findOrFail($id);
        $request->status = 'approved';
        $request->save();

        return redirect()->route('admin.request.index')->with('success', '申請を承認しました。');
    }
}
