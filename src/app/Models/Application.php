<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'date',
        'remarks',
        'accepted',
        'corrected_work_start',
        'corrected_work_end',
        'corrected_rests',
    ];

    protected $dates = ['date'];

    protected $casts = [
        'corrected_rests' => 'array',
        'date' => 'date',
        'accepted' => 'integer',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusTextAttribute(): string
    {
        switch ($this->accepted) {
            case 0: return '承認待ち';
            case 1: return '承認済み';
            default: return '不明';
        }
    }

    public function getFormattedSubjectDateAttribute(): string
    {
        return Carbon::parse($this->date)->format('Y/m/d');
    }

    public function getFormattedApplicationDateAttribute(): string
    {
        return Carbon::parse($this->created_at)->format('Y/m/d');
    }
}
