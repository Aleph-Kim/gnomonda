<?php

namespace App\Models;

use App\Enums\AttendanceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'type', 'time'])]
class AttendanceRecord extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'type' => AttendanceType::class,
        ];
    }
}
