<?php

return [
    /*
     * Automatic "Hot" ranking (php artisan stories:refresh-hot, scheduled hourly).
     * Stories an admin pinned on or off are never touched by it.
     */
    'hot' => [
        // How many days of activity count.
        'window_days' => (int) env('STORIES_HOT_WINDOW_DAYS', 7),

        // How many stories the automatic ranking marks as hot.
        'limit' => (int) env('STORIES_HOT_LIMIT', 20),

        // Minimum activity in the window (reads x 3 + views) to qualify, so a quiet site
        // does not mark stories with one visit as hot.
        'min_score' => (int) env('STORIES_HOT_MIN_SCORE', 10),
    ],
];
