<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class AdminUserListTest extends TestCase
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

    public function test_admin_can_view_all_general_users_in_staff_list()
    {
        // 管理者としてログイン
        $this->actingAs($this->admin, 'admin');

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

        // スタッフ一覧ページにアクセス
        $response = $this->get(route('admin.staff.list'));

        $response->assertStatus(200);

        // 各ユーザーの氏名とメールアドレスが表示されていることを確認
        foreach ($users as $user) {
            $response->assertSee($user->name);
            $response->assertSee($user->email);
        }
    }

    public function test_admin_can_view_selected_users_attendance_list()
    {
        // 管理者としてログイン
        $this->actingAs($this->admin, 'admin');

        $date = now()->toDateString();

        // 一般ユーザーを作成
        $user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        // 勤怠データを1件作成（表示確認用）
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => now()->startOfMonth()->addDays(3),
            'started_at' => '09:00',
            'ended_at' => '18:00',
        ]);

        // 月別勤怠ページにアクセス
        $response = $this->get('/admin/attendance/staff/monthly?user_id=' . $user->id);

        // ステータスと内容を確認
        $response->assertStatus(200);
        $response->assertSee($user->name);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    public function test_admin_can_view_previous_month_attendance_list()
    {
        // 管理者としてログイン
        $this->actingAs($this->admin, 'admin');

        // 一般ユーザーを作成
        $user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        // 前月の年月を取得
        $previousMonth = now()->subMonth()->format('Y-m');
        $workDate = \Carbon\Carbon::parse($previousMonth)->startOfMonth()->addDays(5)->toDateString();


        // 勤怠データを前月分で作成
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => $previousMonth,
        ]);

        // 勤怠一覧ページに前月指定でアクセス
        $monthParam = \Carbon\Carbon::parse($previousMonth)->format('Y-m');
        $response = $this->get('/admin/attendance/list?month=' . $monthParam);

        // ステータスと内容を確認
        $response->assertStatus(200);
        $response->assertSee($user->name);
    }

    public function test_admin_can_view_next_month_attendance_list()
    {
        // 管理者としてログイン
        $this->actingAs($this->admin, 'admin');

        // 一般ユーザーを作成
        $user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        // 翌月の日付を取得
        $nextMonth = now()->addMonth()->format('Y-m');
        $workDate = Carbon::parse($nextMonth)->startOfMonth()->addDays(5)->toDateString();

        // 勤怠データを翌月分で作成
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => $nextMonth,
        ]);

        // 勤怠一覧ページに翌月指定でアクセス
        $response = $this->get('/admin/attendance/list?date=' . $nextMonth);

        // ステータスと内容を確認
        $response->assertStatus(200);
        $response->assertSee($user->name);
    }

    public function test_admin_can_navigate_to_attendance_detail_page()
    {
        // 管理者としてログイン
        $this->actingAs($this->admin, 'admin');

        // 一般ユーザーを作成
        $user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        // 勤怠対象日
        $targetDate = now()->toDateString();

        // 勤怠データを作成
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => $targetDate,
            'started_at' => '09:00',
            'ended_at' => '18:00',
        ]);

        // 勤怠一覧ページにアクセス
        $listResponse = $this->get('/admin/attendance/list?date=' . $targetDate);
        $listResponse->assertStatus(200);

        // 「詳細」リンクのURLを構築
        $detailUrl = '/admin/attendance/detail?date=' . $targetDate . '&user_id=' . $user->id;

        // 詳細ページにアクセス
        $detailResponse = $this->get($detailUrl);
        $detailResponse->assertStatus(200);

        // 詳細ページに勤怠情報が表示されていることを確認
        $detailResponse->assertSee($user->name);
        $detailResponse->assertSee('09:00');
        $detailResponse->assertSee('18:00');
    }
}
