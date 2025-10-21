<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // テストユーザーを明示的に作成
        $this->user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        dump($this->user->email_verified_at);
    }

    /** 出勤中のステータス確認 */
    public function test_status_is_working_when_clocked_in()
    {
        $this->actingAs($this->user);

        Attendance::create([
            'user_id' => $this->user->id,
            'started_at' => now(),
            'work_date' => now()->toDateString(),
        ]);

        $response = $this->get(route('attendance'));
        $response->assertSee('出勤中');
    }

    /** 休憩中のステータス確認 */
    public function test_status_is_on_break_when_break_started()
    {
        $this->actingAs($this->user);

        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'started_at' => now(),
            'work_date' => now()->toDateString(),
        ]);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'started_at' => now(),
        ]);

        $response = $this->get(route('attendance'));
        $response->assertSee('休憩中');
    }

    /** 退勤済のステータス確認 */
    public function test_status_is_clocked_out_when_ended()
    {
        $this->actingAs($this->user);

        Attendance::create([
            'user_id' => $this->user->id,
            'started_at' => now(),
            'ended_at' => now()->subHours(8),
            'work_date' => now()->toDateString(),
        ]);

        $response = $this->get(route('attendance'));
        $response->assertSee('退勤済');
    }

    /** 勤務外のステータス確認（出勤記録なし） */
    public function test_status_is_outside_work_when_no_attendance()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('attendance'));
        $response->assertSee('勤務外');
    }
}