<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use app\Models\BreakTime;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceDetailTest extends TestCase
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

    public function test_attendance_detail_shows_logged_in_user_name()
    {
        // ログイン
        $this->actingAs($this->user);

        // 勤怠データを登録（user_id付き）
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => Carbon::now()->toDateString(),
            'started_at' => Carbon::now()->setTime(9, 0),
            'ended_at' => Carbon::now()->setTime(18, 0),
        ]);

        // 詳細ページへアクセス
        $response = $this->get(route('attendance.detail', ['id' => $attendance->id]));

        // 名前欄にログインユーザーの名前が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee($this->user->name);
    }

    public function test_attendance_detail_shows_today_as_work_date()
    {
        // ログイン
        $this->actingAs($this->user);

        // 実行日（今日）を取得
        $today = Carbon::now();

        // 勤怠データを登録
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => $today->toDateString(),
            'started_at' => $today->copy()->setTime(9, 0),
            'ended_at' => $today->copy()->setTime(18, 0),
        ]);

        // 詳細ページへアクセス
        $response = $this->get(route('attendance.detail', ['id' => $attendance->id]));

        // 日付欄に今日の日付が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee($today->format('Y年'));
        $response->assertSee($today->format('n月j日'));
        $response->assertSee('value="' . $today->format('Y-m-d') . '"', false);
    }

    public function test_attendance_detail_shows_correct_clock_times()
    {
        // ログイン
        $this->actingAs($this->user);

        // 出勤・退勤時間を定義
        $startTime = Carbon::now()->setTime(9, 15);
        $endTime = Carbon::now()->setTime(18, 45);

        // 勤怠データを登録
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => Carbon::now()->toDateString(),
            'started_at' => $startTime,
            'ended_at' => $endTime,
        ]);

        // 詳細ページへアクセス
        $response = $this->get(route('attendance.detail', ['id' => $attendance->id]));

        // 出勤・退勤欄に打刻時間が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee('value="' . $startTime->format('H:i') . '"', false);
        $response->assertSee('value="' . $endTime->format('H:i') . '"', false);
    }

    public function test_attendance_detail_shows_break_time_correctly()
    {
        // ログイン
        $this->actingAs($this->user);

        // 勤怠データを登録
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => Carbon::now()->toDateString(),
            'started_at' => Carbon::now()->setTime(9, 0),
            'ended_at' => Carbon::now()->setTime(18, 0),
        ]);

        // 休憩時間を登録（例：12:00〜12:45）
        $breakStart = Carbon::now()->setTime(12, 0);
        $breakEnd = Carbon::now()->setTime(12, 45);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'started_at' => $breakStart,
            'ended_at' => $breakEnd,
        ]);

        // 詳細ページへアクセス
        $response = $this->get(route('attendance.detail', ['id' => $attendance->id]));

        // 休憩欄に打刻時間が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee('value="' . $breakStart->format('H:i') . '"', false);
        $response->assertSee('value="' . $breakEnd->format('H:i') . '"', false);
    }
}
