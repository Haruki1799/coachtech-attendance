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
        $this->actingAs($this->user);

        $response = $this->get('/attendance');
        $response->assertStatus(200);

        $expectedDate = Carbon::now()->isoFormat('YYYY年M月D日(ddd)');

        $response->assertSee($expectedDate);
    }
}