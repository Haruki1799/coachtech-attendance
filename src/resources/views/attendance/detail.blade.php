@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/detail.css') }}">
@endsection

@section('content')
<div class="container">
    <h2>勤怠詳細</h2>

    <form method="POST" action="{{ $attendance->exists
        ? route('attendance.detail', ['id' => $attendance->id])
        : route('attendance.detail') }}">
        @csrf
        @if ($attendance->exists)
        @method('PUT')
        @endif

        <table class="detail-table">
            <tbody>
                <tr>
                    <th>名前</th>
                    <td>{{ $attendance->user->name ?? auth()->user()->name }}</td>
                </tr>
                <tr>
                    <th>日付</th>
                    <td>
                        <div class="date-inline">
                            <div>{{ $attendance->work_date->format('Y年') }}</div>
                            <div>{{ $attendance->work_date->format('n月j日') }}</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>出勤・退勤</th>
                    <td>
                        <input type="time" name="started_at"
                            value="{{ optional($attendance->started_at)->format('H:i') }}"
                            style="width: 100px; margin-right: 12px;">
                        〜
                        <input type="time" name="ended_at"
                            value="{{ optional($attendance->ended_at)->format('H:i') }}"
                            style="width: 100px;">

                        @error('started_at')
                        <div class="error">{{ $message }}</div>
                        @enderror
                        @error('ended_at')
                        <div class="error">{{ $message }}</div>
                        @enderror
                    </td>
                </tr>

                {{-- 登録済みの休憩時間 --}}
                @foreach ($attendance->breakTimes as $index => $break)
                <tr>
                    <th>休憩{{ $index + 1 }}</th>
                    <td>
                        <input type="time" name="breaks[{{ $index }}][started_at]"
                            value="{{ $break->started_at ? \Carbon\Carbon::parse($break->started_at)->format('H:i') : '' }}"
                            style="width: 100px; margin-right: 12px;">
                        〜
                        <input type="time" name="breaks[{{ $index }}][ended_at]"
                            value="{{ $break->ended_at ? \Carbon\Carbon::parse($break->ended_at)->format('H:i') : '' }}"
                            style="width: 100px;">

                        @error("breaks.$index.started_at")
                        <div class="error">{{ $message }}</div>
                        @enderror
                        @error("breaks.$index.ended_at")
                        <div class="error">{{ $message }}</div>
                        @enderror
                    </td>
                </tr>
                @endforeach

                {{-- 追加用の空欄1件 --}}
                @php $nextIndex = $attendance->breakTimes->count(); @endphp
                <tr>
                    <th>休憩{{ $nextIndex + 1 }}</th>
                    <td>
                        <input type="time" name="breaks[{{ $nextIndex }}][started_at]"
                            style="width: 100px; margin-right: 12px;">
                        〜
                        <input type="time" name="breaks[{{ $nextIndex }}][ended_at]"
                            style="width: 100px;">

                        @error("breaks.$nextIndex.started_at")
                        <div class="error">{{ $message }}</div>
                        @enderror
                        @error("breaks.$nextIndex.ended_at")
                        <div class="error">{{ $message }}</div>
                        @enderror
                    </td>
                </tr>

                <tr>
                    <th>備考</th>
                    <td>
                        <textarea name="note" rows="3" style="width: 100%;"
                            placeholder="備考を入力">{{ old('note', $attendance->note) }}</textarea>
                        @error('note')
                        <div class="error">{{ $message }}</div>
                        @enderror
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="detail-actions">
            <button type="submit" class="btn btn-dark">修正</button>
        </div>
    </form>
</div>
@endsection