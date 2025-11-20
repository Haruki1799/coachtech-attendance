<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function test_user_can_see_clockin_button_and_clockin()
    {
        $this->actingAs($this->user);

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);

        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤');

        $clockinResponse = $this->post('/attendance/clockin');
        $clockinResponse->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);
    }

    /** @test */
    public function test_user_who_already_clockedout_cannot_see_clockin_button()
    {
        Carbon::setTestNow(Carbon::parse('2025-10-27 09:00:00'));

        $this->actingAs($this->user);

        Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->subHours(8),
            'ended_at' => now()->subHours(1),
        ]);

        $response = $this->get('/attendance');
        $response->assertStatus(200);

        $response->assertDontSee('btn-attendance-start');
        $response->assertDontSee('出勤');
    }

    /** @test */
    public function test_user_can_clockin_and_see_clockin_date_in_attendance_list()
    {
        Carbon::setTestNow(Carbon::parse('2025-10-27 09:00:00'));

        $this->actingAs($this->user);

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);

        $this->post('/attendance/clockin')->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);

        $response = $this->get('/attendance/list?year=2025&month=10');
        $response->assertStatus(200);
        $response->assertSee('10/27');
    }
}
