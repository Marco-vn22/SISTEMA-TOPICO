<?php

return [
    'session_hours' => max(1, (int) env('TOPICO_SESSION_HOURS', 8)),
    'idle_minutes' => max(5, (int) env('TOPICO_IDLE_MINUTES', 30)),
];
