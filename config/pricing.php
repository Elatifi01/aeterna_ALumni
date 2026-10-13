<?php

return [

    'base_monthly' => 19.00,

    'units' => [
        'alumni' => ['per' => 100, 'price' => 4.00, 'unlimited' => 120.00],
        'events' => ['per' => 10, 'price' => 3.00, 'unlimited' => 40.00],
        'campaigns' => ['per' => 10, 'price' => 3.00, 'unlimited' => 40.00],
        'users' => ['per' => 1, 'price' => 2.00, 'unlimited' => 60.00],
    ],

    'features' => [
        'alumni_portal' => [
            'label' => 'Portail alumni',
            'description' => 'Comptes alumni, inscription aux evenements et messagerie',
            'price' => 29.00,
        ],
    ],

    'yearly_months' => 10,

];
