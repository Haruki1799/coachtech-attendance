<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => '管理者',
            'email' => 'root@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
    }
    public function test_admin_can_view_all_users_attendance_for_today()
    {
        // 管理者としてログイン
        $this->actingAs($this->admin, 'admin');

        $today = now()->toDateString();

        // 一般ユーザーを複数作成
        $users = [
            User::create([
                'name' => 'テストユーザ',
                'email' => 'test@gmail.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role' => 'user',
            ]),
            User::create([
                'name' => 'テストユーザ2',
                'email' => 'test2@gmail.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role' => 'user',
            ]),
        ];

        // 各ユーザーの勤怠データを作成
        foreach ($users as $user) {
            Attendance::create([
                'user_id' => $user->id,
                'work_date' => $today,
                'started_at' => now()->setTime(9, 0),
                'ended_at' => now()->setTime(18, 0),
            ]);
        }

        // 管理者が勤怠一覧画面にアクセス
        $response = $this->get(route('admin.list'));

        $response->assertStatus(200);

        // 各ユーザーの勤怠情報が表示されていることを確認
        foreach ($users as $user) {
            $response->assertSee($user->name);
            $response->assertSee('09:00');
            $response->assertSee('18:00');
            $response->assertSee(\Carbon\Carbon::parse($today)->format('Y/m/d'));
        }
    }

    public function test_attendance_list_displays_today_date_for_admin()
    {
        // 管理者としてログイン
        $this->actingAs($this->admin, 'admin');

        // 今日の日付（表示形式に合わせて）
        $todayFormatted = now()->format('Y年m月d日'); // 例：2025年11月11日

        // 勤怠一覧画面にアクセス
        $response = $this->get(route('admin.list'));

        // ステータスと日付表示を確認
        $response->assertStatus(200);
        $response->assertSee($todayFormatted);
    }

    public function test_admin_can_view_previous_day_attendance()
    {
        // 管理者としてログイン
        $this->actingAs($this->admin, 'admin');

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // 前日分の勤怠データを作成
        $user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => $yesterday,
            'started_at' => now()->subDay()->setTime(9, 0),
            'ended_at' => now()->subDay()->setTime(18, 0),
        ]);

        // 勤怠一覧画面に「前日」パラメータ付きでアクセス
        $response = $this->get(route('admin.list', ['date' => $yesterday]));

        $response->assertStatus(200);

        // 前日の日付と勤怠情報が表示されていることを確認
        $response->assertSee(\Carbon\Carbon::parse($yesterday)->format('Y年m月d日'));
        $response->assertSee('テストユーザ');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    public function test_admin_can_view_next_day_attendance()
    {
        // 管理者としてログイン
        $this->actingAs($this->admin, 'admin');

        $tomorrow = now()->addDay()->toDateString();

        // 翌日分の勤怠データを作成
        $user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => $tomorrow,
            'started_at' => now()->addDay()->setTime(9, 0),
            'ended_at' => now()->addDay()->setTime(18, 0),
        ]);

        // 勤怠一覧画面に「翌日」パラメータ付きでアクセス
        $response = $this->get(route('admin.list', ['date' => $tomorrow]));

        $response->assertStatus(200);

        // 翌日の日付と勤怠情報が表示されていることを確認
        $response->assertSee(\Carbon\Carbon::parse($tomorrow)->format('Y年m月d日'));
        $response->assertSee('テストユーザ');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }
}
