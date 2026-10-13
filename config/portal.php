<?php

return [

    'initial_password_expires_days' => env('PORTAL_INITIAL_PASSWORD_EXPIRES_DAYS', 30),

    'generated_password_length' => 12,

    'reset_link_expires_minutes' => 60,

];
