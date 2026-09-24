<?php

use App\Enums\UserRole;

return [

    // FR-018: only this exact domain signs in; subdomains and look-alikes do not.
    'allowed_email_domain' => env('ALLOWED_EMAIL_DOMAIN', 'cas.ai'),

    // FR-021: applied once, when the account is created; later role changes go through user:role.
    'admin_emails' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),

    // FR-021: the role of every other new account; from() throws on a typo instead of silently falling back.
    'default_role' => UserRole::from((string) env('DEFAULT_USER_ROLE', UserRole::Member->value)),

    'api_log_retention_days' => (int) env('API_LOG_RETENTION_DAYS', 90),

];
