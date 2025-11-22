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

    public function test_user_can_see_clockin_button_and_clockin()
    {
        // ログイン状態の準備
        $this->actingAs($this->user);

        // 前提確認：まだ今日の勤怠データが存在しない
        $this->assertDatabaseMissing('attendances', [
            'user_id'   => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);

        // 勤怠画面にアクセスして「出勤」ボタンが見えることを確認
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤');

        // 出勤処理を実行（POSTリクエスト
        $clockinResponse = $this->post('/attendance/clockin');
        $clockinResponse->assertRedirect('/attendance');

        // 出勤後：DBに今日の勤怠データが保存されていることを確認
        $this->assertDatabaseHas('attendances', [
            'user_id'   => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);
    }


    public function test_user_who_already_clockedout_cannot_see_clockin_button()
    {

        // テスト用ユーザーでログイン状態にする
        $this->actingAs($this->user);

        // すでに「出勤→退勤」済みの勤怠データを作成
        Attendance::create([
            'user_id'   => $this->user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->subHours(8),
            'ended_at'  => now()->subHours(1),
        ]);

        // 勤怠画面にアクセス
        $response = $this->get('/attendance');

        // ページが正常に表示されることを確認
        $response->assertStatus(200);

        // 退勤済みなので「出勤ボタン」が表示されないことを確認
        $response->assertDontSee('btn-attendance-start'); // ボタンのクラスが存在しない
        $response->assertDontSee('出勤');                 // 出勤ラベルが存在しない
    }

    public function test_user_can_clockin_and_see_clockin_date_in_attendance_list()
    {
        $this->actingAs($this->user);

        // 今日の勤怠データがまだ存在しないことを確認
        $this->assertDatabaseMissing('attendances', [
            'user_id'   => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);

        // 出勤処理を実行
        $this->post('/attendance/clockin')->assertRedirect('/attendance');

        // 出勤後に今日の勤怠データが保存されていることを確認
        $this->assertDatabaseHas('attendances', [
            'user_id'   => $this->user->id,
            'work_date' => now()->toDateString(),
        ]);

        // 勤怠一覧画面にアクセス
        $year  = now()->year;
        $month = now()->month;
        $day   = now()->format('m/d');

        $response = $this->get("/attendance/list?year={$year}&month={$month}");
        $response->assertStatus(200);
        $response->assertSee($day);
    }
}
