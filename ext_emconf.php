<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Service: Geocoding via Google Maps',
    'description' => 'Provides services for google maps GeoCoding API and radius search on the database.',
    'category' => 'sv',
    'author' => 'Benjamin Mack',
    'author_email' => 'benjamin.mack@b13.com',
    'author_company' => 'b13 GmbH',
    'shy' => '',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '5.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-12.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
