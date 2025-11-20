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

    public function test_user_can_see_clockout_button_and_clockout()
    {
        $this->actingAs($this->user);

        // 勤務中の勤怠レコード（退勤していない）
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->subHours(8),
            'ended_at' => null,
        ]);

        // 退勤ボタンが表示されていることを確認
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('退勤');
        $response->assertSee('btn-attendance-end');

        // 退勤処理を実行
        $clockoutResponse = $this->post('/attendance/clockout');
        $clockoutResponse->assertRedirect('/attendance');

        // 勤怠レコードが更新されたことを確認
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'ended_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_user_can_clockin_and_clockout_and_see_clockout_date_in_list()
    {
        $this->actingAs($this->user);

        // 勤怠が存在しないことを確認（勤務外）
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);

        // 出勤処理
        $this->post('/attendance/clockin')->assertRedirect('/attendance');

        // 勤怠レコードが作成されたことを確認
        $attendance = Attendance::where('user_id', $this->user->id)
            ->whereDate('work_date', now()->toDateString())
            ->first();

        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->started_at);
        $this->assertNull($attendance->ended_at);

        // 退勤処理
        Carbon::setTestNow(Carbon::parse('2025-10-27 18:00:00')); // 退勤時間に更新
        $this->post('/attendance/clockout')->assertRedirect('/attendance');

        // 勤怠レコードが更新されたことを確認
        $attendance->refresh();
        $this->assertNotNull($attendance->ended_at);
        $this->assertEquals(now()->toDateTimeString(), $attendance->ended_at->toDateTimeString());

        // 勤怠一覧画面にアクセスし、退勤日が表示されていることを確認
        $response = $this->get('/attendance/list?year=2025&month=10');
        $response->assertStatus(200);
        $response->assertSee('10/27');
    }
}
