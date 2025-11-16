<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendancesTableSeeder extends Seeder
{
    public function run()
    {
        User::all()->each(function ($user) {

            for ($i = 0; $i < 30; $i++) {
                $date = Carbon::today()->subDays($i);

                Attendance::create([
                    'user_id'    => $user->id,
                    'work_date'  => $date->format('Y-m-d'),
                    'started_at' => $date->copy()->setTime(9, 0),
                    'ended_at'   => $date->copy()->setTime(18, 0),
                ]);
            }
        });
    }
}
