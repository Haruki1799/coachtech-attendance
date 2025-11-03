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

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
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
        $response->assertSessionHasErrors(['started_at']);

        $errors = session('errors');
        $this->assertEquals(
            '出勤時間もしくは退勤時間が不適切な値です',
            $errors->first('started_at')
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
}
