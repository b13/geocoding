<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Service: Geocoding via Google Maps',
    'description' => 'Provides services for google maps GeoCoding API and radius search on the database.',
    'category' => 'sv',
    'author' => 'Benjamin Mack',
    'author_email' => 'benjamin.mack@b13.com',
    'author_company' => 'b13 GmbH',
    'state' => 'stable',
    'version' => '5.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
