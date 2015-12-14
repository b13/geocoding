<?php

defined('TYPO3_MODE') or die();

// Define state cache, if not already defined
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['geocoding'])) {
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['geocoding'] = array(
		'frontend' => 'TYPO3\\CMS\\Core\\Cache\\Frontend\\VariableFrontend',
		'backend'  => 'TYPO3\\CMS\\Core\\Cache\\Frontend\\Typo3DatabaseBackend',
	);
}