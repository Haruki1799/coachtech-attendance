<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'started_at' => ['required', 'date_format:H:i'],
            'ended_at' => ['nullable', 'date_format:H:i', 'after_or_equal:started_at'],
            'note' => ['required', 'string'],

            'breaks.*.started_at' => ['nullable', 'date_format:H:i'],
            'breaks.*.ended_at' => ['nullable', 'date_format:H:i', 'after_or_equal:breaks.*.started_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'started_at.required' => '出勤時間が不適切な値です',
            'started_at.date_format' => '出勤時間が不適切な値です',

            'ended_at.date_format' => '退勤時間が不適切な値です',
            'ended_at.after_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',

            'breaks.*.started_at.date_format' => '休憩時間が不適切な値です',
            'breaks.*.ended_at.date_format' => '休憩時間が不適切な値です',
            'breaks.*.ended_at.after_or_equal' => '休憩時間が不適切な値です',

            'note.required' => '備考を記入してください',
        ];
    }
}