<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Attendance extends Model
{
    protected $fillable = ['user_id', 'date', 'work_start', 'work_end', 'total_work'];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rests()
    {
        return $this->hasMany(Rest::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function getTotalRestTimeAttribute(): string
    {
        if (! $this->relationLoaded('rests')) {
            $this->load('rests');
        }

        $totalSeconds = 0;
        foreach ($this->rests as $rest) {
            if ($rest->rest_start && $rest->rest_end) {
                try {
                    $start = Carbon::parse($this->date->format('Y-m-d') . ' ' . $rest->rest_start);
                    $end = Carbon::parse($this->date->format('Y-m-d') . ' ' . $rest->rest_end);

                    if ($end->greaterThan($start)) {
                        $totalSeconds += $end->diffInSeconds($start);
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not calculate rest time for rest ID: ' . $rest->id . ' on attendance ID: ' . $this->id . ' Error: ' . $e->getMessage());
                    continue;
                }
            }
        }

        if ($totalSeconds > 0) {
            $hours = floor($totalSeconds / 3600);
            $minutes = floor(($totalSeconds % 3600) / 60);
            return sprintf('%02d:%02d', $hours, $minutes);
        } else {
            return '00:00';
        }
    }

    public function getActualWorkTimeAttribute(): ?string
    {
        if (!$this->work_start || !$this->work_end) {
            return null;
        }

        try {
            $workStart = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->work_start);
            $workEnd = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->work_end);

            if ($workEnd->lessThanOrEqualTo($workStart)) {
                return '00:00';
            }
            $totalWorkSeconds = $workEnd->diffInSeconds($workStart);

            $totalRestSeconds = 0;
            if (! $this->relationLoaded('rests')) {
                $this->load('rests');
            }
            foreach ($this->rests as $rest) {
                if ($rest->rest_start && $rest->rest_end) {
                    try {
                        $start = Carbon::parse($this->date->format('Y-m-d') . ' ' . $rest->rest_start);
                        $end = Carbon::parse($this->date->format('Y-m-d') . ' ' . $rest->rest_end);
                        if ($end->greaterThan($start)) {
                            $totalRestSeconds += $end->diffInSeconds($start);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Could not parse rest time in getActualWorkTimeAttribute for rest ID: ' . $rest->id . ' on attendance ID: ' . $this->id . ' Error: ' . $e->getMessage());
                        continue;
                    }
                }
            }

            $actualWorkSeconds = $totalWorkSeconds - $totalRestSeconds;

            if ($actualWorkSeconds < 0) {
                $actualWorkSeconds = 0;
            }

            $hours = floor($actualWorkSeconds / 3600);
            $minutes = floor(($actualWorkSeconds % 3600) / 60);
            return sprintf('%02d:%02d', $hours, $minutes);

        } catch (\Exception $e) {
            Log::error('Could not calculate actual work time for attendance ID: ' . $this->id . ' Error: ' . $e->getMessage());
            return null;
        }
    }
}
