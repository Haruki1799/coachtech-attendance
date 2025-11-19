<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'started_at' => ['required', 'sometimes', 'date_format:H:i'],
            'ended_at' => ['nullable', 'sometimes', 'date_format:H:i'],
            'note' => ['required', 'string'],

            'breaks.*.started_at' => ['nullable', 'sometimes', 'date_format:H:i'],
            'breaks.*.ended_at' => ['nullable', 'sometimes', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'started_at.required' => '出勤時間が不適切な値です',
            'started_at.date_format' => '出勤時間が不適切な値です',

            'ended_at.date_format' => '退勤時間が不適切な値です',

            'breaks.*.started_at.date_format' => '休憩時間が不適切な値です',
            'breaks.*.ended_at.date_format' => '休憩時間が不適切な値です',

            'note.required' => '備考を記入してください',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $workDate = $this->input('work_date') ?? now()->toDateString();
            $startedAt = $this->input('started_at');
            $endedAt = $this->input('ended_at');
            $breaks = $this->input('breaks', []);

            $start = $startedAt ? Carbon::parse("{$workDate} {$startedAt}") : null;
            $end = $endedAt ? Carbon::parse("{$workDate} {$endedAt}") : null;

            if ($start && $end) {
                if ($start->gt($end)) {
                    $validator->errors()->add('started_at', '出勤時間もしくは退勤時間が不適切な値です');
                }
                if ($end->lt($start)) {
                    $validator->errors()->add('ended_at', '出勤時間もしくは退勤時間が不適切な値です');
                }
            }

            foreach ($breaks as $index => $break) {
                $bStart = isset($break['started_at']) ? Carbon::parse("{$workDate} {$break['started_at']}") : null;
                $bEnd = isset($break['ended_at']) ? Carbon::parse("{$workDate} {$break['ended_at']}") : null;

                if ($bStart) {
                    if (($start && $bStart->lt($start)) || ($end && $bStart->gt($end))) {
                        $validator->errors()->add("breaks.$index.started_at", '休憩時間が不適切な値です');
                    }
                }

                if ($bEnd) {
                    if ($end && $bEnd->gt($end)) {
                        $validator->errors()->add("breaks.$index.ended_at", '休憩時間もしくは退勤時間が不適切な値です');
                    }
                    if ($bStart && $bStart->gt($bEnd)) {
                        $validator->errors()->add("breaks.$index.ended_at", '休憩終了は休憩開始より後にしてください');
                    }
                }
            }
        });
    }
}
