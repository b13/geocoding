<?php
$extensionPath = t3lib_extMgm::extPath('geocoding');
return array(
	'tx_geocoding_service_geoservice' => $extensionPath . 'Classes/Service/GeoService.php',
	'tx_geocoding_service_radiusservice' => $extensionPath . 'Classes/Service/RadiusService.php',
);
