<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class BreakTimeTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2025-10-27 10:00:00'));

        // テストユーザーを明示的に作成
        $this->user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
    }


    public function test_user_can_see_breakin_button_and_start_break()
    {
        $this->actingAs($this->user);

        // 出勤済の勤怠レコード（退勤していない）
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->subHour(),
            'ended_at' => null,
        ]);

        // 「休憩入」ボタンが表示されていることを確認
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');
        $response->assertSee('btn-break-start');

        // 休憩処理を実行
        $breakResponse = $this->post('/attendance/breakin');
        $breakResponse->assertRedirect('/attendance');

        // 休憩レコードが作成されていることを確認
        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'started_at' => now()->toDateTimeString(),
        ]);
    }


    public function test_user_can_breakin_and_breakout_and_see_breakin_button_again()
    {
        $this->actingAs($this->user);

        // 出勤済の勤怠レコード（退勤していない）
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->subHour(),
            'ended_at' => null,
        ]);

        // 休憩開始
        $this->post('/attendance/breakin')->assertRedirect('/attendance');

        // 休憩レコードが作成されたことを確認
        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'started_at' => now()->toDateTimeString(),
            'ended_at' => null,
        ]);

        // 休憩終了
        $this->post('/attendance/breakout')->assertRedirect('/attendance');

        // 休憩レコードが更新されたことを確認
        $this->assertDatabaseMissing('break_times', [
            'attendance_id' => $attendance->id,
            'ended_at' => null,
        ]);

        // 再び休憩入ボタンが表示されることを確認
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');
        $response->assertSee('btn-break-start');
    }

    public function test_user_can_perform_breakin_and_breakout_process()
    {
        $this->actingAs($this->user);

        // 出勤済の勤怠レコード（退勤していない）
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->subHour(),
            'ended_at' => null,
        ]);

        // 休憩開始（breakin）
        $this->post('/attendance/breakin')->assertRedirect('/attendance');

        // 休憩レコードが作成されたことを確認
        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'started_at' => now()->toDateTimeString(),
            'ended_at' => null,
        ]);

        // 休憩終了（breakout）
        $this->post('/attendance/breakout')->assertRedirect('/attendance');

        // 休憩レコードが更新されたことを確認
        $this->assertDatabaseMissing('break_times', [
            'attendance_id' => $attendance->id,
            'ended_at' => null,
        ]);

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'started_at' => now()->toDateTimeString(),
            'ended_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_user_can_enter_second_break_and_see_breakout_button()
    {
        $this->actingAs($this->user);

        // 出勤済の勤怠レコード（退勤していない）
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->subHour(),
            'ended_at' => null,
        ]);

        // 1回目の休憩開始
        $this->post('/attendance/breakin')->assertRedirect('/attendance');

        // 1回目の休憩終了
        $this->post('/attendance/breakout')->assertRedirect('/attendance');

        // 2回目の休憩開始
        $this->post('/attendance/breakin')->assertRedirect('/attendance');

        // 休憩戻ボタンが表示されることを確認（休憩中状態）
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩戻');
        $response->assertSee('btn-break-end');
    }

    public function test_user_can_perform_break_and_see_break_date_in_attendance_list()
    {
        $this->actingAs($this->user);

        // 出勤済の勤怠レコード（退勤していない）
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->subHour(), // 9:00 出勤
            'ended_at' => null,
        ]);

        // 休憩開始
        $this->post('/attendance/breakin')->assertRedirect('/attendance');

        // 休憩終了
        $this->post('/attendance/breakout')->assertRedirect('/attendance');

        // 勤怠一覧画面にアクセス
        $response = $this->get('/attendance/list?year=2025&month=10');
        $response->assertStatus(200);

        // 休憩日が表示されていることを確認（Blade側で m/d 表記）
        $response->assertSee('10/27');
    }
}
