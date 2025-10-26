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

        // テストユーザーを明示的に作成
        $this->user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function 勤務外ユーザーが出勤ボタンを見て出勤できる()
    {
        // ログイン
        $this->actingAs($this->user);

        // 1. 勤怠が存在しないことを確認
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);

        // 2. 出勤画面にアクセスし、「出勤」ボタンが表示されていることを確認
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤');

        // 3. 出勤処理を実行
        $clockinResponse = $this->post('/attendance/clockin');
        $clockinResponse->assertRedirect('/attendance');

        // 勤怠レコードが作成されていることを確認
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);
    }

    /** @test */
    public function 退勤済ユーザーには出勤ボタンが表示されない()
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

        // 出勤ボタンが表示されないことを確認
        $response->assertDontSee('btn-attendance-start');
        $response->assertDontSee('出勤');
    }

    /** @test */
    public function 勤務外ユーザーが出勤し勤怠一覧で出勤日を確認できる()
    {
        // テスト日付を固定
        Carbon::setTestNow(Carbon::parse('2025-10-27 09:00:00'));

        // ログイン
        $this->actingAs($this->user);

        // 勤怠が存在しないことを確認
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);

        // 出勤処理を実行
        $this->post('/attendance/clockin')->assertRedirect('/attendance');

        // 勤怠レコードが作成されたことを確認
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);

        // 勤怠一覧画面にアクセス
        $response = $this->get('/attendance/list?year=2025&month=10');
        $response->assertStatus(200);

        // 出勤日が表示されていることを確認（Blade側で m/d 表記）
        $response->assertSee('10/27');
    }
}