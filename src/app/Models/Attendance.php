<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'started_at',
        'ended_at',
        'work_date',
    ];

    protected $casts = [
        'work_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_submitted' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function breakTimes()
    {
        return $this->hasMany(BreakTime::class);
    }

    public function getTotalHoursAttribute()
    {
        if (!$this->started_at || !$this->ended_at) return null;

        $workMinutes = $this->ended_at->diffInMinutes($this->started_at);

        $breakMinutes = $this->breakTimes->sum(function ($break) {
            return Carbon::parse($break->ended_at)->diffInMinutes(Carbon::parse($break->started_at));
        });

        $netMinutes = max(0, $workMinutes - $breakMinutes);
        return sprintf('%d:%02d', intdiv($netMinutes, 60), $netMinutes % 60);
    }
}
