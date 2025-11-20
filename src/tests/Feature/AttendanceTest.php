<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class ClockOutTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2025-10-27 17:00:00'));

        $this->user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
    }

    public function test_user_can_clockout_when_working()
    {
        $this->actingAs($this->user);

        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->subHours(8),
            'ended_at' => null,
        ]);

        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('退勤');
        $response->assertSee('btn-attendance-end');

        $clockoutResponse = $this->post('/attendance/clockout');
        $clockoutResponse->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'ended_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_user_can_clockin_and_clockout_and_see_attendance_list()
    {
        $this->actingAs($this->user);

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);

        $this->post('/attendance/clockin')->assertRedirect('/attendance');

        $attendance = Attendance::where('user_id', $this->user->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();

        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->started_at);
        $this->assertNull($attendance->ended_at);

        Carbon::setTestNow(Carbon::parse('2025-10-27 18:00:00'));
        $this->post('/attendance/clockout')->assertRedirect('/attendance');

        $attendance->refresh();
        $this->assertNotNull($attendance->ended_at);
        $this->assertEquals(now()->toDateTimeString(), $attendance->ended_at->toDateTimeString());

        $response = $this->get('/attendance/list?year=2025&month=10');
        $response->assertStatus(200);
        $response->assertSee('10/27');
    }
}
