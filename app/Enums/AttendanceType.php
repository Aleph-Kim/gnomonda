<?php

namespace App\Enums;

enum AttendanceType: string
{
    // 출근
    case CheckIn = 'check_in';
    // 퇴근
    case CheckOut = 'check_out';
}
