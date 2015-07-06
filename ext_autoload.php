<?php
if (class_exists(t3lib_extMgm)) {
    $extensionPath = t3lib_extMgm::extPath('geocoding');
} else {
    $extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('geocoding');
}

$fp = fopen('/home/christoph/web/apps/t6/geocoding.log');
fwrite($fp, $extensionPath);
fclose($fp);

return array(
    'B13\Geocoding\Service\GeoService' => $extensionPath.'Classes/Service/GeoService.php',
    'B13\Geocoding\Service\RadiusService' => $extensionPath.'Classes/Service/RadiusService.php',
);
