<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

// compatibility for 4.5
if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) < '4006000') {

	if (TYPO3_MODE == 'BE') {
		
		// register the cache in BE so it will be cleared with "clear all caches"
		try {
			t3lib_cache::initializeCachingFramework();
			// State cache
			$GLOBALS['typo3CacheFactory']->create(
				'tx_geocoding',
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_geocoding']['frontend'],
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_geocoding']['backend'],
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_geocoding']['options']
			);
		} catch(t3lib_cache_exception_NoSuchCache $exception) {
		}
	}
}