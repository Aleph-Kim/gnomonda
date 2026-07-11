<?php

namespace App\Models;

use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'check_in_time', 'check_out_time', 'meeting'])]
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    protected $attributes = [
        'meeting' => false,
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'meeting' => 'boolean',
        ];
    }
}
