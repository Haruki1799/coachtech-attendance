<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;
use Carbon\Carbon;

class AttendancesTableSeeder extends Seeder
{
    public function run()
    {
        User::where('role', 'user')->get()->each(function ($user) {

            for ($i = 0; $i < 30; $i++) {
                $date = Carbon::today()->subDays($i);

                $attendance = Attendance::create([
                    'user_id'    => $user->id,
                    'work_date'  => $date->format('Y-m-d'),
                    'started_at' => $date->copy()->setTime(9, 0),
                    'ended_at'   => $date->copy()->setTime(18, 0),
                ]);

                BreakTime::create([
                    'attendance_id' => $attendance->id,
                    'started_at'    => $date->copy()->setTime(12, 0),
                    'ended_at'      => $date->copy()->setTime(13, 0),
                ]);

                BreakTime::create([
                    'attendance_id' => $attendance->id,
                    'started_at'    => $date->copy()->setTime(15, 0),
                    'ended_at'      => $date->copy()->setTime(15, 15),
                ]);
            }
        });
    }
}
