<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class AdminAttendanceDetailTest extends TestCase
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

    public function test_admin_can_view_correct_attendance_detail()
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

        // 勤怠データを作成
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'started_at' => now()->setTime(9, 0),
            'ended_at' => now()->setTime(18, 0),
        ]);

        // 管理者が勤怠詳細ページにアクセス
        $response = $this->get(route('admin.attendance.admin_detail', ['id' => $attendance->id]));

        $response->assertStatus(200);

        // 表示内容が勤怠データと一致していることを確認
        $response->assertSee('テストユーザ');
        $response->assertSee(\Carbon\Carbon::parse($date)->format('Y年'));
        $response->assertSee(\Carbon\Carbon::parse($date)->format('m月d日'));
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    public function test_admin_sees_error_when_started_at_is_after_ended_at()
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


        // 勤怠データを作成
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'started_at' => '09:00',
            'ended_at' => '18:00',
        ]);

        // 不正な時間で更新リクエストを送信（出勤 > 退勤）
        $response = $this->from(route('admin.attendance.admin_detail', ['id' => $attendance->id]))
            ->put(route('admin.attendance.update', ['id' => $attendance->id]), [
                'user_id' => $user->id,
                'work_date' => $date,
                'started_at' => '19:00',
                'ended_at' => '09:00',
                'note' => '不正な時間テスト',
            ]);

        // リダイレクトとバリデーションメッセージを確認
        $response->assertRedirect(route('admin.attendance.admin_detail', ['id' => $attendance->id]));
        $response->assertSessionHasErrors([
            'started_at' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);
    }

    public function test_admin_sees_error_when_break_start_is_after_ended_at()
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

        // 勤怠データを作成
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'started_at' => '09:00',
            'ended_at' => '18:00',
        ]);

        // 不正な休憩時間（開始が退勤後）で更新リクエストを送信
        $response = $this->from(route('admin.attendance.admin_detail', ['id' => $attendance->id]))
            ->put(route('admin.attendance.update', ['id' => $attendance->id]), [
                'user_id' => $user->id,
                'work_date' => $date,
                'started_at' => '09:00',
                'ended_at' => '18:00',
                'breaks' => [
                    [
                        'started_at' => '19:00',
                        'ended_at' => '20:00',
                    ],
                ],
                'note' => '休憩時間バリデーションテスト',
            ]);

        // リダイレクトとバリデーションメッセージを確認
        $response->assertRedirect(route('admin.attendance.admin_detail', ['id' => $attendance->id]));
        $response->assertSessionHasErrors([
            'breaks.0.started_at' => '休憩時間が不適切な値です',
        ]);
    }

    public function test_admin_sees_error_when_break_end_is_after_ended_at()
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

        // 勤怠データを作成
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'started_at' => '09:00',
            'ended_at' => '18:00',
        ]);

        // 不正な休憩終了時間（退勤後）で更新リクエストを送信
        $response = $this->from(route('admin.attendance.admin_detail', ['id' => $attendance->id]))
            ->put(route('admin.attendance.update', ['id' => $attendance->id]), [
                'user_id' => $user->id,
                'work_date' => $date,
                'started_at' => '09:00',
                'ended_at' => '18:00',
                'breaks' => [
                    [
                        'started_at' => '17:00',
                        'ended_at' => '19:00', // ← 退勤後
                    ],
                ],
                'note' => '休憩終了時間バリデーションテスト',
            ]);

        // リダイレクトとバリデーションメッセージを確認
        $response->assertRedirect(route('admin.attendance.admin_detail', ['id' => $attendance->id]));
        $response->assertSessionHasErrors([
            'breaks.0.ended_at' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    public function test_admin_sees_error_when_note_is_empty()
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

        // 勤怠データを作成
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'started_at' => '09:00',
            'ended_at' => '18:00',
        ]);

        // 備考未入力で保存処理を送信
        $response = $this->from(route('admin.attendance.admin_detail', ['id' => $attendance->id]))
            ->put(route('admin.attendance.update', ['id' => $attendance->id]), [
                'user_id' => $user->id,
                'work_date' => $date,
                'started_at' => '09:00',
                'ended_at' => '18:00',
                'note' => '', // ← 未入力
            ]);

        // リダイレクトとバリデーションメッセージを確認
        $response->assertRedirect(route('admin.attendance.admin_detail', ['id' => $attendance->id]));
        $response->assertSessionHasErrors([
            'note' => '備考を記入してください',
        ]);
    }
}
