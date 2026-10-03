<?php

return [
    'log_days' => (int) env('SECURITY_LOG_DAYS', 30),
    'log_stderr' => filter_var(env('SECURITY_LOG_STDERR', false), FILTER_VALIDATE_BOOL),
    'generate_idempotency_seconds' => (int) env('GENERATE_IDEMPOTENCY_SECONDS', 5),
];
