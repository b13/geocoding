<?php

defined('TYPO3_MODE') or die();

// Define state cache, if not already defined
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['geocoding'] ?? false)) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['geocoding'] = [
        'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
        'backend'  => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
    ];
}
