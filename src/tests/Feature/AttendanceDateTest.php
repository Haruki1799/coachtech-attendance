<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AttendanceDateTest extends TestCase
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
        dump($this->user->email_verified_at);
    }

    public function test_attendance_page_shows_today_date()
    {
        // ログイン状態の準備
        $this->actingAs($this->user);

        // 勤怠画面にアクセス
        $response = $this->get('/attendance');
        $response->assertStatus(200);

        // 期待される今日の日付を生成
        $expectedDate = Carbon::now()->isoFormat('YYYY年M月D日(ddd)');

        // 勤怠画面に今日の日付が表示されていることを確認
        $response->assertSee($expectedDate);
    }
}