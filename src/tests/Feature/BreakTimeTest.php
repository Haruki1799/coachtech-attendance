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

    /** @test */
    public function 出勤中ユーザーが休憩入ボタンを見て休憩できる()
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

    /** @test */
    public function 出勤中ユーザーが休憩入と休憩戻を行い再び休憩入ボタンが表示される()
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
    /** @test */
    public function 出勤中ユーザーが休憩入と休憩戻の処理を行える()
    {
        $this->actingAs($this->user);

        // 出勤済の勤怠レコード（退勤していない）
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->subHour(), // 9:00 出勤
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
    /** @test */
    public function 出勤中ユーザーが2回目の休憩に入り休憩戻ボタンが表示される()
    {
        $this->actingAs($this->user);

        // 出勤済の勤怠レコード（退勤していない）
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->subHour(), // 9:00 出勤
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

    /** @test */
    public function 勤務中ユーザーが休憩処理を行い勤怠一覧で休憩日を確認できる()
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
