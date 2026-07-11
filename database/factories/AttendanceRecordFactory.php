<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'check_in_time' => $this->timeAround('09:30', 30),
            'check_out_time' => $this->timeAround('18:30', 45),
            'meeting' => fake()->boolean(15),
        ];
    }

    /**
     * 미팅만 있고 출퇴근 기록은 없는 날.
     */
    public function meetingOnly(): static
    {
        return $this->state(fn () => [
            'check_in_time' => null,
            'check_out_time' => null,
            'meeting' => true,
        ]);
    }

    /**
     * 기준 시각(HH:MM)에서 ±$varianceMinutes 범위 안의 임의 시각을 만든다.
     */
    private function timeAround(string $base, int $varianceMinutes): string
    {
        [$hour, $minute] = array_map('intval', explode(':', $base));
        $offset = fake()->numberBetween(-$varianceMinutes, $varianceMinutes);
        $totalMinutes = max(0, min(23 * 60 + 59, $hour * 60 + $minute + $offset));

        return sprintf('%02d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60);
    }
}
