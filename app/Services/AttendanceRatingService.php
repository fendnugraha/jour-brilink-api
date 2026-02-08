<?php

namespace App\Services;

use Illuminate\Support\Collection;

class AttendanceRatingService
{
    protected array $scoreMap = [
        'good' => 10,       // datang paling awal
        'approved' => 9,    // hadir tepat waktu
        'late' => 2,        // telat (berapa menit pun sama)
    ];

    public function calculateFromAttendances(Collection $attendances): array
    {
        $totalDays = $attendances->count();

        if ($totalDays === 0) {
            return [
                'total_days' => 0,
                'good' => 0,
                'approved' => 0,
                'late' => 0,
                'rating' => 0,
            ];
        }

        $counts = [
            'good' => $attendances->where('approval_status', 'Good')->count(),
            'approved' => $attendances->where('approval_status', 'Approved')->count(),
            'late' => $attendances->where('approval_status', 'Late')->count(),
        ];

        $totalScore = 0;

        foreach ($counts as $status => $count) {
            $totalScore += $count * $this->scoreMap[$status];
        }

        $rating = round(($totalScore / $totalDays) * 2) / 2;

        return [
            'total_days' => $totalDays,
            'good' => $counts['good'],
            'approved' => $counts['approved'],
            'late' => $counts['late'],
            'rating' => min($rating, 10), // safety
        ];
    }
}
