<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceListTest extends TestCase
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

    //勤怠情報が全て表示されてるか
    public function test_user_can_view_own_attendance_list()
    {
        $this->actingAs($this->user);

        $dates = [
            Carbon::create(now()->year, now()->month, 1),
            Carbon::create(now()->year, now()->month, 2),
            Carbon::create(now()->year, now()->month, 3),
        ];

        foreach ($dates as $date) {
            Attendance::create([
                'user_id' => $this->user->id,
                'work_date' => $date->toDateString(),
                'started_at' => $date->copy()->setTime(9, 0),
                'ended_at' => $date->copy()->setTime(18, 0),
            ]);
        }

        $response = $this->get('/attendance/list');

        foreach ($dates as $date) {
            $response->assertSee($date->format('m/d') . '(' . $date->isoFormat('ddd') . ')');
            $response->assertSee('09:00');
            $response->assertSee('18:00');
        }

        $response->assertStatus(200);
    }

    public function test_attendance_list_shows_current_month()
    {
        // ユーザーでログインして
        $this->actingAs($this->user);

        // 勤怠一覧ページへアクセス
        $response = $this->get(route('attendance.list'));

        // 現在の年月を取得
        $currentMonth = Carbon::now()->format('Y/m');

        // 表示内容に現在の月が含まれていることを確認
        $response->assertStatus(200);
        $response->assertSee($currentMonth);
    }

    public function test_previous_month_attendance_is_displayed()
    {
        // ユーザーログイン
        $this->actingAs($this->user);

        // 前月の日付を取得
        $previousMonthDate = Carbon::now()->subMonth()->day(15);

        // 勤怠一覧ページに前月指定でアクセス
        $response = $this->get(route('attendance.list', [
            'year' => $previousMonthDate->year,
            'month' => $previousMonthDate->month,
        ]));

        // 月の年月が表示されていることを確認
        $expectedMonthLabel = $previousMonthDate->format('Y/m');
        $response->assertStatus(200);
        $response->assertSee($expectedMonthLabel);
    }

    public function test_next_month_attendance_is_displayed()
    {
        // ユーザーログイン
        $this->actingAs($this->user);

        // 翌月の日付を取得
        $nextMonthDate = Carbon::now()->addMonth()->day(15);

        // 勤怠一覧ページに翌月指定でアクセス
        $response = $this->get(route('attendance.list', [
            'year' => $nextMonthDate->year,
            'month' => $nextMonthDate->month,
        ]));

        // 翌月の年月が表示されていることを確認
        $expectedMonthLabel = $nextMonthDate->format('Y/m');
        $response->assertStatus(200);
        $response->assertSee($expectedMonthLabel);
    }

    public function test_attendance_detail_navigation_from_list()
    {
        // ユーザーでログインして
        $this->actingAs($this->user);

        // 今日の日付で勤怠データを登録
        $today = Carbon::now()->toDateString();
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => Carbon::now()->toDateString(),
            'started_at' => Carbon::now()->setTime(9, 0),
            'ended_at' => Carbon::now()->setTime(18, 0),
        ]);

        // 勤怠一覧ページへアクセス
        $response = $this->get(route('attendance.list'));

        // 詳細リンクが表示されていることを確認
        $detailUrl = route('attendance.detail', ['id' => $attendance->id]);
        $response->assertStatus(200);
        $response->assertSee($detailUrl);

        // 詳細リンクにアクセスして詳細画面が表示されることを確認
        $detailResponse = $this->get($detailUrl);
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('勤怠詳細');
        $detailResponse->assertSee($this->user->name);
        $detailResponse->assertSee(Carbon::parse($attendance->work_date)->format('Y年'));
    }
}
