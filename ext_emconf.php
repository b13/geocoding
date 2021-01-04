<?php

//#######################################################################
// Extension Manager/Repository config file for ext "geocoding".
//
// Auto generated 27-09-2012 15:06
//
// Manual updates:
// Only the data in the array - everything else is removed by next
// writing. "version" and "dependencies" must not be touched!
//#######################################################################

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
    'version' => '4.2.0',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-11.5.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
