<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Providers\RouteServiceProvider;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 会員登録時、名前が未入力の場合はバリデーションエラーとなることを確認するテスト
     */
    public function name_is_required_for_registration()
    {
        $userData = [
            'email' => 'testuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->post(route('register'), $userData);

        $response->assertInvalid(['name']);

        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください'
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'testuser@example.com',
        ]);
    }

    /**
     * @test
     * 会員登録時、メールアドレスが未入力の場合はバリデーションエラーとなることを確認するテスト
     */
    public function email_is_required_for_registration()
    {
        $userData = [
            'name' => 'テストユーザー',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->post(route('register'), $userData);

        $response->assertInvalid(['email']);

        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください'
        ]);

        $this->assertDatabaseMissing('users', [
            'name' => 'テストユーザー',
        ]);
    }

    /**
     * @test
     * 会員登録時、パスワードが８文字未満の場合はバリデーションエラーとなることを確認するテスト
     */
    public function password_is_min8_for_registration()
    {
        $userData = [
            'name' => 'テストユーザー',
            'email' => 'testuser@example.com',
            'password' => 'pass',
            'password_confirmation' => 'pass',
        ];

        $response = $this->post(route('register'), $userData);

        $response->assertInvalid(['password']);

        $response->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください'
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'testuser@example.com',
        ]);
    }

    /**
     * @test
     * 会員登録時、パスワードと確認用パスワードが一致しない場合はバリデーションエラーとなることを確認するテスト
     */
    public function password_confirmation_does_not_match()
    {
        $userData = [
            'name' => 'テストユーザー',
            'email' => 'testuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'differentpassword',
        ];

        $response = $this->post(route('register'), $userData);

        $response->assertInvalid(['password']);

        $response->assertSessionHasErrors([
            'password' => 'パスワードと一致しません'
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'testuser@example.com',
        ]);
    }

    /**
     * @test
     * 会員登録時、パスワードが未入力の場合はバリデーションエラーとなることを確認するテスト
     */
    public function password_is_required_for_registration()
    {
        $userData = [
            'name' => 'テストユーザー',
            'email' => 'testuser@example.com',
        ];

        $response = $this->post(route('register'), $userData);

        $response->assertInvalid(['password']);

        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください'
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'testuser@example.com',
        ]);
    }

    /**
     * @test
     * 正しい情報が入力された場合、会員登録が成功することを確認するテスト
     */
    public function user_can_register_with_valid_data()
    {
        $userData = [
            'name' => 'テストユーザー',
            'email' => 'testuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->post(route('register'), $userData);

        $response->assertValid();

        $this->assertAuthenticated();

        $response->assertRedirect(RouteServiceProvider::HOME);

        $this->assertDatabaseHas('users', [
            'name' => 'テストユーザー',
            'email' => 'testuser@example.com',
        ]);
    }
}
