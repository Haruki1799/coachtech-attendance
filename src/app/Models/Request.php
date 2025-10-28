<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Request extends Model
{
    protected $fillable = [
        'attendance_id',
        'user_id',
        'target_date',
        'reason',
        'status',
        'requested_at',
    ];

    protected $casts = [
        'target_date' => 'date',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'pending' => '申請待ち',
            'approved' => '承認済み',
            'rejected' => '差戻し',
            default => '不明',
        };
    }
}