<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 b:dreizehn, Germany <typo3@b13.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
/**
 * calculate the radius between two addresses etc
 *
 * @package Tx_Geocoding
 * @subpackage Service
 */
class Tx_Geocoding_Service_RadiusService {

	/**
	 * calculates the distance in kilometers between two coordinates
	 *
	 * @param array $coordinates1 an associative array with "latitude" and "longitude"
	 * @param array $coordinates2 an associative array with "latitude" and "longitude"
	 * @return float
	 */
	public function getDistance($coordinates1, $coordinates2) {
		$distance = (3958 * 3.1415926 * sqrt(($coordinates2['latitude']-$coordinates1['latitude'])*($coordinates2['latitude']-$coordinates1['latitude']) + cos($coordinates2['latitude']/57.29578)*cos($coordinates1['latitude']/57.29578)*($coordinates2['longitude']-$coordinates1['longitude'])*($coordinates2['longitude']-$coordinates1['longitude']))/180);
		$distance = $distance * 1.60934400061469;
		return $distance;
	}
	
	/**
	 * fetches all records within a certain radius of given coordinates
	 * see http://spinczyk.net/blog/2009/10/04/radius-search-with-google-maps-and-mysql/
	 *
	 * @return array
	 */
	public function findAllDatabaseRecordsInRadius($coordinates, $tableName = 'pages', $maxDistance = 250) {

			// earth radius in kilometers
		$earthRadius = 6380;
		
		$distanceSqlCalc = 'ACOS(SIN(RADIANS(latitude)) * SIN(RADIANS(' . $coordinates['latitude'] . ')) + COS(RADIANS(latitude)) * COS(RADIANS(' . $coordinates['latitude'] . ')) * COS(RADIANS(longitude) - RADIANS(' . $coordinates['longitude'] . '))) * ' . $earthRadius;

		$records = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, ' . $distanceSqlCalc . ' AS distance',
			$tableName,
			$distanceSqlCalc . ' < ' . $maxDistance,
			'',	// group by
			'distance ASC'
		);
		return $records;
	}


}