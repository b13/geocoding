<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

// Define state cache, if not already defined
if (!is_array($TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['tx_geocoding'])) {
    $TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['tx_geocoding'] = array(
        'frontend' => 't3lib_cache_frontend_VariableFrontend',
        'backend' => 't3lib_cache_backend_DbBackend',
    );
}

// Compatibility for 4.5
if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) < '4006000') {
    $TYPO3_CONF_VARS['SYS']['useCachingFramework']                                       = '1';
    $TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['tx_geocoding']['options']
        = array(
        'cacheTable' => 'tx_geocoding',
        'tagsTable' => 'tx_geocoding_tags',
    );
}