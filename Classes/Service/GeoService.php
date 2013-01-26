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
 * calculate the geo coordinates of an address
 *
 * @package Tx_Geocoding
 * @subpackage Service
 */
class Tx_Geocoding_Service_GeoService {

	protected $apikey = '';

	protected $cacheTime = 7776000;	// 90 days

	/**
	 * base URL to fetch the Coordinates (Latitude, Longitutde of a Address String
	 */
	protected $geocodingUrl = 'http://maps.google.com/maps/geo?sensor=false&oe=utf8&gl=en&output=csv';

	/**
	 * base URL to fetch all information about an address (add &address=...) 
	 * see http://maps.googleapis.com/maps/api/geocode/json?sensor=true&address=70182,+Germany
	 * for an example
	 */
	protected $baseApiUrl = 'http://maps.googleapis.com/maps/api/geocode/json?sensor=true';


	/**
	 * set the google maps API key
	 */
	public function __construct($apikey = NULL) {
			// load from extension configuration
		if ($apikey === NULL) {
			$geoCodingConfig = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['geocoding']);
			$apikey = $geoCodingConfig['googleApiKey'];
		}
		$this->apikey = $apikey;
		$this->geocodingUrl .= '&key=' . $apikey;
	}

	/**
	 * uses the "old" CSV query of Google
	 * core functionality: asks google for the coordinates of an address
	 * stores known addresses in a local cache
	 *
	 * @param $street
	 * @param $zip
	 * @param $city
	 * @param $country
	 * @return array an array with accuracy, latitude and longitude
	 */
	public function getCoordinatesForAddress($street = NULL, $zip = NULL, $city = NULL, $country = 'Germany') {
		$results = NULL;

			// get a full name (e.g. "Germany") from a country code
		$country = $this->getCountryFromPrefix($country);
	
		$address = $street . ', ' . $zip . ' ' . $city . ', ' . $country;
		$address = trim($address, ', ');	// remove trailing commas and whitespaces

		if ($address) {
			$cacheObject = $this->initializeCache();

				// create the cache key
			$cacheKey = 'geocode-' . strtolower(str_replace(' ', '-', preg_replace("/[^0-9a-zA-Z ]/m", '', $address)));

				// not in cache yet
			if (!$cacheObject->has($cacheKey)) {
		
				$geocodingUrl = $this->geocodingUrl . '&q=' . urlencode($address);
				$results = t3lib_div::getUrl($geocodingUrl);

				list($loc, $accuracy, $latitude, $longitude) = explode(',', $results);

				$results = array(
					'accuracy'  => $accuracy,
					'latitude'  => $latitude,
					'longitude' => $longitude
				);

				if ($latitude != 0) {
						// Now store the $result in cache and return
					$cacheObject->set($cacheKey, $results, array(), $this->cacheTime);
				}
			} else {
				$results = $cacheObject->get($cacheKey);
			}
		}
		return $results;
	}
	

	/**
	 * geocodes all missing records in a DB table and then stores the values
	 * in the DB record
	 *
	 * only works if your DB table has the necessary fields
	 * helpful when calculating a batch of addresses and save the latitude/longitude automatically
	 */
	public function calculateCoordinatesForAllRecordsInTable($tableName, $latitudeField = 'latitude', $longitudeField = 'longitude', $streetField = 'street', $zipField = 'zip', $cityField = 'city', $countryField = 'country', $addWhereClause = '') {

			// fetch all records without latitude/longitude
		$records = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			$tableName,
			'deleted=0 AND
			(' . $latitudeField . ' IS NULL OR ' . $latitudeField . '=0 OR ' . $latitudeField . '=0.00000000000
				OR ' . $longitudeField . ' IS NULL OR ' . $longitudeField . '=0 OR ' . $longitudeField . '=0.00000000000) '  . $addWhereClause,
			'',	// group by
			'',	// order by
			'100'	// limit
		);

		t3lib_div::loadTCA($tableName);

		if (count($records) > 0) {
			foreach ($records as $record) {
			
				$country = $record[$countryField];
					// resolve the label for the country
				if ($GLOBALS['TCA'][$tableName]['columns'][$countryField]['config']['type'] == 'select') {
					foreach ($GLOBALS['TCA'][$tableName]['columns'][$countryField]['config']['items'] as $itm) {
						if ($itm[1] == $country) {
							if (is_object($GLOBALS['TSFE'])) {
								$country = $GLOBALS['TSFE']->sL($itm[0]);
							} else {
								$country = $GLOBALS['LANG']->sL($itm[0]);
							}
						}
					}
				}
					// do the geocoding
				$coords = $this->getCoordinatesForAddress($record[$streetField], $record[$zipField], $record[$cityField], $country);
				if ($coords) {
						// update the record to fill in the latitude and longitude values in the DB
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
						$tableName,
						'uid=' . intval($record['uid']),
						array(
							$latitudeField => $coords['latitude'],
							$longitudeField => $coords['longitude']
						)
					);
				}
			}
		}
		return count($records);
	}



	/**
	 * fetches the city of a ZIP
	 * uses the JSON query string
	 */
	public function getCityFromZip($zip, $country = 'Germany', $street = NULL) {
		$results = NULL;

			// get a full name (e.g. "Germany") from a country code
		$country = $this->getCountryFromPrefix($country);
	
		$address = $street . ', ' . $zip . ', ' . $country;
		$address = trim($address, ', ');	// remove trailing commas and whitespaces

		if ($address) {
			$cacheObject = $this->initializeCache();

				// create the cache key
			$cacheKey = 'geocodecityfromzip-' . strtolower(str_replace(' ', '-', preg_replace("/[^0-9a-zA-Z ]/m", '', $address)));

				// not in cache yet
			if (!$cacheObject->has($cacheKey)) {
		
				$geocodingUrl = $this->baseApiUrl . '&address=' . urlencode($address);
				$results = t3lib_div::getUrl($geocodingUrl);
				$results = json_decode($results);
				var_dump($results);
				exit;

				$results = array(
					'accuracy'  => $accuracy,
					'latitude'  => $latitude,
					'longitude' => $longitude
				);

				if ($latitude != 0) {
						// Now store the $result in cache and return
					$cacheObject->set($cacheKey, $results, array(), $this->cacheTime);
				}
			} else {
				$results = $cacheObject->get($cacheKey);
			}
		}
		return $results;
	}




	/**
	 * initializes the cache for the DB requests
	 *
	 * @return Cache Object
	 */
	protected function initializeCache() {
		// Create the cache (only needed for 4.5 and lower)
		if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) < '4006000') {

	        t3lib_cache::initializeCachingFramework();
	        try {
	            $cacheInstance = $GLOBALS['typo3CacheManager']->getCache('tx_geocoding');
	        } catch (t3lib_cache_exception_NoSuchCache $e) {
	            $cacheInstance = $GLOBALS['typo3CacheFactory']->create(
	                'tx_geocoding',
	                $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_geocoding']['frontend'],
	                $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_geocoding']['backend'],
	                $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_geocoding']['options']
	            );
	        }
		} else {

			// Initialize the cache
			try {
				$cacheInstance = $GLOBALS['typo3CacheManager']->getCache('tx_geocoding');
			} catch(t3lib_cache_exception_NoSuchCache $e) {
				throw new Exception('Unable to load Cache! 1299944198');
			}
		}

		return $cacheInstance;
	}

	/**
	 * helper function to get the international common name
	 * to use for google coding
	 */
	protected function getCountryFromPrefix($country) {
		switch ($country) {
			case 'A':
			case 'AT':
				$fullCountry = 'Austria';
			break;
			case 'D':
			case 'DE':
				$fullCountry = 'Germany';
			break;
			case 'CH':
				$fullCountry = 'Switzerland';
			break;
			case 'UK':
				$fullCountry = 'Great Britain';
			break;
			case 'B':
			case 'BE':
				$fullCountry = 'Belgium';
			break;
			case 'IT':
				$fullCountry = 'Italy';
			break;
			case 'L':
			case 'LUX':
				$fullCountry = 'Luxemburg';
			break;
			default:
				$fullCountry = $country;
		}
		return $fullCountry;
	}

}