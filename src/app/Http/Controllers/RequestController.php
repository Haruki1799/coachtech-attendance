<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request as HttpRequest;
use App\Models\Request as RequestModel;

class RequestController extends Controller
{
    public function index(HttpRequest $request)
    {
        $status = $request->input('status', 'pending');

        $requests = RequestModel::with('user')
            ->when(in_array($status, ['pending', 'approved', 'rejected']), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('requested_at')
            ->get();

        return view('requestlist', compact('requests'));
    }
}
