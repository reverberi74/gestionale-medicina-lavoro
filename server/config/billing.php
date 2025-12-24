<?php

return [
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    // Quali status sono considerati "access allowed" prima della scadenza effettiva (con grace)
    'allowed_statuses' => ['trial', 'active', 'past_due'],
];
