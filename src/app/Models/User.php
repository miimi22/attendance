<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, MustVerifyEmailTrait;

    const STATUS_OFF_WORK = 0;   // 勤務外 (null もこれに該当させる想定)
    const STATUS_ON_WORK = 1;    // 出勤中
    const STATUS_ON_REST = 2;   // 休憩中
    const STATUS_LEFT_WORK = 3;  // 退勤済

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'last_verification_email_sent_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_verification_email_sent_at' => 'datetime',
    ];

    public function isAdmin()
    {
        return $this->role === 1;
    }

    public function getAttendanceStatusAttribute($value)
    {
        return $value ?? self::STATUS_OFF_WORK;
    }

    public function attendances()
    {
        return $this->hasMany(\App\Models\Attendance::class);
    }

    public function sendEmailVerificationNotification()
    {
        $userId = $this->id;
        $cacheKey = 'verification_email_sent_lock_user_' . $userId;

        if (Cache::has($cacheKey)) {
            return;
        }

        $this->notify(new CustomVerifyEmail);

        Cache::put($cacheKey, true, 60);
    }
}
