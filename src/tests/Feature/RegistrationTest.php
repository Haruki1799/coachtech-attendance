<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_receives_verification_email_after_registration()
    {
        // 通知送信をフェイク
        Notification::fake();

        // 会員登録処理を実行
        $response = $this->post('/register', [
            'name' => 'テストユーザ',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // 登録後に認証誘導画面へリダイレクトされることを確認
        $response->assertRedirect(route('verification.notice'));

        // ユーザーがDBに保存されていることを確認
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);

        // 認証メールが送信されていることを確認
        Notification::assertSentTo(
            User::where('email', 'test@example.com')->first(),
            VerifyEmail::class
        );
    }

    public function test_user_can_navigate_to_verification_site()
    {
        $user = User::create([
            'name' => 'テストユーザ',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => null,
        ]);

        $this->actingAs($user);

        $verifyUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // メール認証サイトに遷移することを確認
        $response = $this->get($verifyUrl);
        $response->assertStatus(302);
    }

    public function test_user_is_redirected_to_attendance_after_email_verification()
    {
        // ユーザーを作成（未認証状態）
        $user = User::create([
            'name' => 'テストユーザ',
            'email' => 'verify@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => null,
        ]);

        // ログイン状態にする
        $this->actingAs($user);

        // 署名付きURLを生成
        $verifyUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // 認証リンクにアクセス
        $response = $this->get($verifyUrl);

        // 勤怠登録画面にリダイレクトされることを確認
        $response->assertRedirect('/attendance');

        // 認証済みになっていることを確認
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
