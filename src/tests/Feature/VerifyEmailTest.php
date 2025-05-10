<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;

class VerifyEmailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 会員登録後、登録したメールアドレス宛に認証メールが送信されることを確認するテスト
     *
     * @return void
     */
    public function test_user_receives_custom_verification_email_after_registration_with_fortify()
    {
        Notification::fake();


        $userData = [
            'name' => 'テストユーザー Fortify',
            'email' => 'testuser.fortify@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];


        $response = $this->post(route('register'), $userData);


        $this->assertDatabaseHas('users', [
            'name' => $userData['name'],
            'email' => $userData['email'],
        ]);

        $user = User::where('email', $userData['email'])->first();
        $this->assertNotNull($user, 'ユーザーがデータベースに作成されていません。');
        $this->assertNull($user->email_verified_at, '登録直後はemail_verified_atがnullであるべきです。');

        Notification::assertSentTo(
            [$user],
            CustomVerifyEmail::class
        );

        Notification::assertSentToTimes($user, CustomVerifyEmail::class, 1);

        $response->assertRedirect(config('fortify.home'));


        $this->assertAuthenticatedAs($user);
    }

    /**
     * メール認証誘導画面で「認証はこちらから」ボタンを押したらメール認証サイトに遷移することを確認するテスト
     *
     * @return void
     */
    public function test_verification_notice_page_shows_correct_mailhog_link_in_local_environment()
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('verification.notice'));

        $response->assertStatus(200);
        $response->assertViewIs('auth.verify-email');

        $response->assertSeeText('登録していただいたメールアドレスに認証メールを送付しました。');
        $response->assertSeeText('メール認証を完了してください。');

        $expectedMailhogUrl = config('app.mailhog_url', 'http://localhost:8025');

        if (app()->environment('local')) {
            $response->assertSeeText('認証はこちらから');
            $response->assertSee('href="' . $expectedMailhogUrl . '"', false);
            $response->assertSee('target="_blank"', false);
            $response->assertSee('class="verification"', false);
        } else {
            $response->assertDontSeeText('認証はこちらから');
            $response->assertDontSee('href="' . $expectedMailhogUrl . '"', false);
        }

        $response->assertSee('認証メールを再送する');
        $response->assertSee('action="' . route('verification.send') . '"', false);
        $response->assertSee('method="POST"', false);
    }

    /**
     * メール認証サイトのメール認証を完了すると勤怠画面に遷移することを確認するテスト
     *
     * @return void
     */
    public function test_clicking_verification_link_verifies_email_and_redirects_to_attendance_screen()
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this->actingAs($user)->get($verificationUrl);


        $user->refresh();
        $this->assertNotNull($user->email_verified_at, 'email_verified_atが更新されていません。');

        Event::assertDispatched(Verified::class, function ($event) use ($user) {
            return $event->user->is($user);
        });

        $response->assertRedirect(route('attendance', ['verified' => 1]));

        $followResponse = $this->actingAs($user)->get(route('attendance'));
        $followResponse->assertStatus(200);
    }
}
