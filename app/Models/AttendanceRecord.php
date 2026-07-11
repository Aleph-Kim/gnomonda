<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'check_in_time', 'check_out_time', 'meeting'])]
class AttendanceRecord extends Model
{
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
