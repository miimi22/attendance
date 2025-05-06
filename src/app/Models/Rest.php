<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rest extends Model
{
    protected $table = 'rests';

    protected $fillable = ['attendance_id', 'rest_start', 'rest_end'];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}
