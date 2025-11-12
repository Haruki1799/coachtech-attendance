@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/requestlist.css') }}">
@endsection

@section('content')
<div class="container">
    <h2>申請一覧</h2>

    <div class="status-tabs mb-3">
        <a href="{{ route('request.index', ['status' => 'pending']) }}"
            class="status-tab {{ request('status') === 'pending' ? 'active' : '' }}">
            承認待ち
        </a>
        <a href="{{ route('request.index', ['status' => 'approved']) }}"
            class="status-tab {{ request('status') === 'approved' ? 'active' : '' }}">
            承認済み
        </a>
    </div>

    <div class="divider"></div>

    <table class="table">
        <thead>
            <tr>
                <th>状態</th>
                <th>名前</th>
                <th>対象日時</th>
                <th>申請理由</th>
                <th>申請日時</th>
                <th>詳細</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($requests as $request)
            <tr>
                <td>{{ $request->status_label }}</td>
                <td>{{ $request->user->name }}</td>
                <td>{{ optional($request->target_date)->format('Y/m/d') }}</td>
                <td>{{ $request->reason }}</td>
                <td>{{ $request->created_at->format('Y/m/d') }}</td>
                <td><a href="{{ route('request.show', ['id' => $request->id]) }}">詳細</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection