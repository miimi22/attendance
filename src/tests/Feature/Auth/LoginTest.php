<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * ログイン時、メールアドレスが未入力の場合はバリデーションエラーとなることを確認するテスト
     */
    public function email_is_required_for_login()
    {
        $user = User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => bcrypt('password123'),
        ]);

        $loginData = [
            'password' => 'password123',
        ];

        $response = $this->post(route('login'), $loginData);

        $response->assertInvalid(['email']);

        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください'
        ]);

        $this->assertGuest();
    }

    /**
     * @test
     * ログイン時、パスワードが未入力の場合はバリデーションエラーとなることを確認するテスト
     */
    public function password_is_required_for_login()
    {
        $user = User::factory()->create([
            'email' => 'testuser@example.com',
            'password' => bcrypt('password123'),
        ]);

        $loginData = [
            'email' => $user->email,
        ];

        $response = $this->post(route('login'), $loginData);

        $response->assertInvalid(['password']);

        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください'
        ]);

        $this->assertGuest();
    }

    /**
     * @test
     * ログイン時、登録内容と一致しないメールアドレスの場合は認証失敗エラーとなることを確認するテスト
     */
    public function login_fails_with_incorrect_email()
    {
        User::factory()->create([
            'email' => 'existinguser@example.com',
            'password' => bcrypt('password123'),
        ]);

        $loginData = [
            'email' => 'nonexistentuser@example.com',
            'password' => 'anypassword',
        ];

        $response = $this->post(route('login'), $loginData);

        $response->assertInvalid(['email']);

        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません'
        ]);

        $this->assertGuest();
    }
}
