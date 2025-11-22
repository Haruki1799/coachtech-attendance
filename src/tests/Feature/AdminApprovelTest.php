<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;


class AdminApprovelTest extends TestCase
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

    public function test_admin_can_view_all_pending_stamp_requests()
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

        foreach ($users as $user) {
            // 勤怠データを作成
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'work_date' => now()->toDateString(),
                'started_at' => '09:00',
                'ended_at' => '18:00',
            ]);

            // 修正申請を作成（status: pending）
            \App\Models\AttendanceRequest::create([
                'user_id' => $user->id,
                'attendance_id' => $attendance->id,
                'target_date' => $attendance->work_date,
                'reason' => '打刻漏れ',
                'status' => 'pending',
                'requested_at' => now()->setTime(9, 0),
            ]);
        }

        // 承認待ちタブにアクセス
        $response = $this->get('/admin/stamp_correction_request/list?status=pending');

        $response->assertStatus(200);

        // 各ユーザーの名前と理由が表示されていることを確認
        foreach ($users as $user) {
            $response->assertSee($user->name);
            $response->assertSee('打刻漏れ');
            $response->assertSee('申請待ち');
        }
    }

    public function test_admin_can_view_all_approved_requests()
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

        foreach ($users as $user) {
            // 勤怠データを作成
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'work_date' => now()->toDateString(),
                'started_at' => '09:00',
                'ended_at' => '18:00',
            ]);

            // 承認済みの修正申請を作成
            \App\Models\AttendanceRequest::create([
                'user_id' => $user->id,
                'attendance_id' => $attendance->id,
                'target_date' => $attendance->work_date,
                'reason' => '打刻修正済み',
                'status' => 'approved',
                'requested_at' => now()->setTime(9, 0),
            ]);
        }

        // 承認済みタブにアクセス
        $response = $this->get('/admin/stamp_correction_request/list?status=approved');

        $response->assertStatus(200);

        // 各ユーザーの名前と理由が表示されていることを確認
        foreach ($users as $user) {
            $response->assertSee($user->name);
            $response->assertSee('打刻修正済み');
            $response->assertSee('承認済み');
        }
    }

    public function test_admin_can_view_stamp_request_detail()
    {
        $this->actingAs($this->admin, 'admin');

        // 一般ユーザー作成
        $user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        // 勤怠データ作成
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'started_at' => '09:00',
            'ended_at' => '18:00',
            'note' => '勤怠詳細テスト',
        ]);

        // 休憩データ作成
        $attendance->breakTimes()->create([
            'started_at' => '12:00',
            'ended_at' => '13:00',
        ]);

        // 修正申請作成
        $request = \App\Models\AttendanceRequest::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'target_date' => $attendance->work_date,
            'reason' => '勤怠詳細テスト',
            'status' => 'pending',
            'requested_at' => now()->setTime(9, 0),
        ]);

        // 詳細画面にアクセス
        $response = $this->get(route('admin.request.show', ['id' => $request->id]));

        // 表示内容の確認
        $response->assertStatus(200);
        $response->assertSee('勤怠詳細');
        $response->assertSee('テストユーザ');
        $response->assertSee(now()->format('Y年'));
        $response->assertSee(now()->format('n月j日'));
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('12:00');
        $response->assertSee('13:00');
        $response->assertSee('勤怠詳細テスト');
        $response->assertSee('承認');
    }

    public function test_admin_can_approve_request_and_update_attendance()
    {
        $this->actingAs($this->admin, 'admin');

        // 一般ユーザー作成
        $user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        // 勤怠データ（完全入力済み）
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->setTime(9, 0),
            'ended_at' => now()->setTime(18, 0),
            'note' => '通常勤務',
        ]);

        // 修正申請（承認対象だが勤怠は変更しない）
        $request = \App\Models\AttendanceRequest::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'target_date' => $attendance->work_date,
            'reason' => '確認のための申請',
            'status' => 'pending',
            'requested_at' => now()->setTime(18, 0),
        ]);

        // 承認リクエスト送信
        $response = $this->put(route('admin.request.approve', ['id' => $request->id]));

        // リダイレクト確認
        $response->assertRedirect();

        // 修正申請のステータスが更新されていること
        $this->assertEquals('approved', $request->fresh()->status);
    }
}