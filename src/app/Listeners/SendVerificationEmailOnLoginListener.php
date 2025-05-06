<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Contracts\Auth\MustVerifyEmail;
// use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SendVerificationEmailOnLoginListener
{
    private static array $sentForUsers = [];

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \Illuminate\Auth\Events\Authenticated  $event
     * @return void
     */
    public function handle(Authenticated $event)
    {
        $user = $event->user;
        $userId = $user->id;
        $cacheKey = 'verification_email_sent_lock_user_' . $userId;


        if (Cache::has($cacheKey)) {
            return;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return;
        }

        if ($user instanceof MustVerifyEmail && !$user->hasVerifiedEmail()) {

            $user->sendEmailVerificationNotification();


        } else {
        }
    }
}
