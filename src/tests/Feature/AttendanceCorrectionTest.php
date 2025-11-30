<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        $this->admin = User::create([
            'name' => '管理者',
            'email' => 'root@gmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
    }

    public function test_started_at_after_ended_at_shows_expected_error_message()
    {
        $this->actingAs($this->user);
        $today = now()->toDateString();

        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->setTime(9, 0),
            'ended_at' => now()->setTime(18, 0),
        ]);

        $response = $this->from(route('attendance.detail', ['id' => $attendance->id]))
            ->put(route('attendance.detail.update', ['id' => $attendance->id]), [
                'started_at' => '19:00',
                'ended_at' => '18:00',
                'work_date' => $today,
                'note' => 'テスト備考',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['time_range']);

        $errors = session('errors');
        $this->assertEquals(
            '出勤時間もしくは退勤時間が不適切な値です',
            $errors->first('time_range')
        );
    }

    public function test_break_start_after_ended_at_shows_expected_error_message()
    {
        $this->actingAs($this->user);
        $today = now()->toDateString();

        // 勤怠データを作成
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'started_at' => now()->setTime(9, 0),
            'ended_at' => now()->setTime(18, 0),
        ]);

        // 休憩開始時間を退勤時間より後に設定
        $response = $this->from(route('attendance.detail', ['id' => $attendance->id]))
            ->put(route('attendance.detail.update', ['id' => $attendance->id]), [
                'started_at' => '09:00',
                'ended_at' => '18:00',
                'work_date' => $today,
                'note' => 'テスト備考',
                'breaks' => [
                    ['started_at' => '18:30', 'ended_at' => '19:00'], // ← 退勤後の休憩
                ],
            ]);

        // バリデーションエラーを検証
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['breaks.0.started_at']);

        $errors = session('errors');
        $this->assertEquals(
            '休憩時間が不適切な値です',
            $errors->first('breaks.0.started_at')
        );
    }

    public function test_break_end_after_ended_at_shows_expected_error_message()
    {
        $this->actingAs($this->user);

        $today = now()->toDateString();

        // 勤怠データを作成
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => $today,
            'started_at' => now()->setTime(9, 0),
            'ended_at' => now()->setTime(18, 0),
        ]);

        // 休憩終了時間を退勤時間より後に設定
        $response = $this->from(route('attendance.detail', ['id' => $attendance->id]))
            ->put(route('attendance.detail.update', ['id' => $attendance->id]), [
                'started_at' => '09:00',
                'ended_at' => '18:00',
                'work_date' => $today,
                'note' => 'テスト備考',
                'breaks' => [
                    ['started_at' => '17:00', 'ended_at' => '18:30'], // ← 退勤後の休憩終了
                ],
            ]);

        // バリデーションエラーを検証
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['breaks.0.ended_at']);

        $errors = session('errors');
        $this->assertEquals(
            '休憩時間もしくは退勤時間が不適切な値です',
            $errors->first('breaks.0.ended_at')
        );
    }

    public function test_note_required_validation_error_when_note_is_empty()
    {
        $this->actingAs($this->user);

        $today = now()->toDateString();

        // 勤怠データを作成
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => $today,
            'started_at' => now()->setTime(9, 0),
            'ended_at' => now()->setTime(18, 0),
        ]);

        // 備考欄を未入力で保存処理
        $response = $this->from(route('attendance.detail', ['id' => $attendance->id]))
            ->put(route('attendance.detail.update', ['id' => $attendance->id]), [
                'started_at' => '09:00',
                'ended_at' => '18:00',
                'work_date' => $today,
                'note' => '', // ← 備考未入力
            ]);

        // バリデーションエラーを検証
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['note']);

        $errors = session('errors');
        $this->assertEquals(
            '備考を記入してください',
            $errors->first('note')
        );
    }

    public function test_attendance_correction_request_is_registered_and_visible_to_admin()
    {
        $this->actingAs($this->user);

        $today = now()->toDateString();

        // 勤怠データを作成
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => $today,
            'started_at' => now()->setTime(9, 0),
            'ended_at' => now()->setTime(18, 0),
        ]);

        // 勤怠修正申請を送信
        $response = $this->put(route('attendance.detail.update', ['id' => $attendance->id]), [
            'started_at' => '10:00',
            'ended_at' => '18:00',
            'work_date' => $today,
            'note' => '修正申請テスト',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success', '勤怠情報を修正・申請しました。');

        // 勤怠修正申請がDBに登録されていることを確認
        $this->assertDatabaseHas('requests', [
            'attendance_id' => $attendance->id,
            'user_id' => $this->user->id,
            'target_date' => $today,
            'reason' => '修正申請テスト',
            'status' => 'pending',
        ]);

        // 管理者としてログインし、申請一覧画面にアクセス
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.request.index'));

        $response->assertStatus(200);
        $response->assertSee('テストユーザ');
        $response->assertSee('修正申請テスト');
        $response->assertSee(Carbon::parse($today)->format('Y/m/d'));
    }

    public function test_user_can_see_all_their_attendance_requests_in_list()
    {
        $this->actingAs($this->user);

        $dates = [
            now()->subDays(2)->toDateString(),
            now()->subDay()->toDateString(),
            now()->toDateString(),
        ];

        foreach ($dates as $date) {
            // 勤怠データを作成
            $attendance = Attendance::create([
                'user_id' => $this->user->id,
                'work_date' => $date,
                'started_at' => now()->setTime(9, 0),
                'ended_at' => now()->setTime(18, 0),
            ]);

            // 勤怠修正申請を送信
            $this->put(route('attendance.detail.update', ['id' => $attendance->id]), [
                'started_at' => '10:00',
                'ended_at' => '18:00',
                'work_date' => $date,
                'note' => "修正申請テスト：{$date}",
            ]);
        }

        // 申請一覧画面にアクセス
        $response = $this->get(route('request.index'));

        $response->assertStatus(200);

        // 各申請が表示されていることを確認
        foreach ($dates as $date) {
            $response->assertSee("修正申請テスト：{$date}");
            $response->assertSee(\Carbon\Carbon::parse($date)->format('Y/m/d'));
        }
    }

    public function test_approved_requests_are_displayed_in_admin_approved_list()
    {
        // 1. ユーザーとしてログインし、複数の勤怠修正申請を送信
        $this->actingAs($this->user);

        $dates = [
            now()->subDays(2)->toDateString(),
            now()->subDay()->toDateString(),
        ];

        foreach ($dates as $date) {
            $attendance = Attendance::create([
                'user_id' => $this->user->id,
                'work_date' => $date,
                'started_at' => now()->setTime(9, 0),
                'ended_at' => now()->setTime(18, 0),
            ]);

            $this->put(route('attendance.detail.update', ['id' => $attendance->id]), [
                'started_at' => '10:00',
                'ended_at' => '18:00',
                'work_date' => $date,
                'note' => "修正申請：{$date}",
            ]);
        }

        // 2. 管理者としてログインし、申請を承認
        $this->actingAs($this->admin, 'admin');

        $requests = \App\Models\AttendanceRequest::where('user_id', $this->user->id)->get();

        foreach ($requests as $request) {
            $this->put(route('admin.request.approve', ['id' => $request->id]));
        }

        // 3. 承認済み一覧画面にアクセス（status=approved）
        $response = $this->get(route('admin.request.index', ['status' => 'approved']));

        $response->assertStatus(200);

        // 4. 各申請が表示されていることを確認
        foreach ($dates as $date) {
            $response->assertSee("修正申請：{$date}");
            $response->assertSee(\Carbon\Carbon::parse($date)->format('Y/m/d'));
        }
    }

    public function test_user_can_access_request_detail_from_list()
    {
        $this->actingAs($this->user);

        $date = now()->toDateString();

        // 勤怠データを作成
        $attendance = Attendance::create([
            'user_id' => $this->user->id,
            'work_date' => $date,
            'started_at' => now()->setTime(9, 0),
            'ended_at' => now()->setTime(18, 0),
        ]);

        // 勤怠修正申請を送信
        $this->put(route('attendance.detail.update', ['id' => $attendance->id]), [
            'started_at' => '10:00',
            'ended_at' => '18:00',
            'work_date' => $date,
            'note' => '詳細画面遷移テスト',
        ]);

        // 申請一覧画面にアクセス
        $response = $this->get(route('request.index'));
        $response->assertStatus(200);
        $response->assertSee('詳細');

        // DBから申請IDを取得
        $request = \App\Models\AttendanceRequest::where('attendance_id', $attendance->id)->first();

        // 「詳細」ボタンのリンク先にアクセス
        $detailResponse = $this->get(route('request.show', ['id' => $request->id]));

        // 詳細画面が表示されることを確認
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('詳細画面遷移テスト');
        $detailResponse->assertSee(\Carbon\Carbon::parse($date)->format('Y年'));
        $detailResponse->assertSee(\Carbon\Carbon::parse($attendance->work_date)->format('n月j日'));
    }
}

