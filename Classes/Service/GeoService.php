<?php

namespace B13\Geocoding\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2013 Benjamin Mack, b:dreizehn, Germany <benjamin.mack@b13.de>
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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * calculate the geo coordinates of an address, using the googe geocoding
 * API, an API key is needed, as this is a server-side process.
 */
class GeoService
{
    protected $apikey = '';

    protected $cacheTime = 7776000;    // 90 days

    /**
     * base URL to fetch the Coordinates (Latitude, Longitutde of a Address String.
     */
    protected $geocodingUrl = 'http://maps.googleapis.com/maps/api/geocode/json?language=de&sensor=false';

    /**
     * constructor method.
     *
     * sets the google code API key
     *
     * @param string $apikey (optional) the API key from google, if empty, the default from the configuration is taken
     */
    public function __construct($apikey = null)
    {
        // load from extension configuration
        if ($apikey === null) {
            $geoCodingConfig = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['geocoding']);
            $apikey = $geoCodingConfig['googleApiKey'];
        }
        $this->apikey = $apikey;
        //$this->geocodingUrl .= '&key=' . $apikey;
    }

    /**
     * core functionality: asks google for the coordinates of an address
     * stores known addresses in a local cache.
     *
     * @param $street
     * @param $zip
     * @param $city
     * @param $country
     *
     * @return array an array with accuracy, latitude and longitude
     */
    public function getCoordinatesForAddress($street = null, $zip = null, $city = null, $country = 'Germany')
    {
        $results = null;

        $address = $street . ', ' . $zip . ' ' . $city . ', ' . $country;
        $address = trim($address, ', ');    // remove trailing commas and whitespaces

        if ($address) {
            $cacheObject = $this->initializeCache();

                // create the cache key
            $cacheKey = 'geocode-' . strtolower(str_replace(' ', '-', preg_replace('/[^0-9a-zA-Z ]/m', '', $address)));

                // not in cache yet
            if (!$cacheObject->has($cacheKey)) {
                $geocodingUrl = $this->geocodingUrl . '&address=' . urlencode($address);
                $results = GeneralUtility::getUrl($geocodingUrl);
                $results = json_decode($results, true);

                $latitude = 0;
                if (count($results['results']) > 0) {
                    $record = reset($results['results']);
                    $geometrics = $record['geometry'];

                    $latitude = $geometrics['location']['lat'];
                    $longitude = $geometrics['location']['lng'];
                }

                if ($latitude != 0) {
                    $results = [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ];
                        // Now store the $result in cache and return
                    $cacheObject->set($cacheKey, $results, [], $this->cacheTime);
                }
            } else {
                $results = $cacheObject->get($cacheKey);
            }
        }

        return $results;
    }

    /**
     * geocodes all missing records in a DB table and then stores the values
     * in the DB record.
     *
     * only works if your DB table has the necessary fields
     * helpful when calculating a batch of addresses and save the latitude/longitude automatically
     */
    public function calculateCoordinatesForAllRecordsInTable($tableName, $latitudeField = 'latitude', $longitudeField = 'longitude', $streetField = 'street', $zipField = 'zip', $cityField = 'city', $countryField = 'country', $addWhereClause = '')
    {

            // fetch all records without latitude/longitude
        $records = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            '*',
            $tableName,
            'deleted=0 AND
            (' . $latitudeField . ' IS NULL OR ' . $latitudeField . '=0 OR ' . $latitudeField . '=0.00000000000
                OR ' . $longitudeField . ' IS NULL OR ' . $longitudeField . '=0 OR ' . $longitudeField . '=0.00000000000)' . $addWhereClause,
            '',    // group by
            '',    // order by
            '500'    // limit
        );

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
                if (!empty($record[$zipField]) || !empty($record[$cityField])) {
                    $coords = $this->getCoordinatesForAddress($record[$streetField], $record[$zipField], $record[$cityField], $country);
                    if ($coords) {
                        // update the record to fill in the latitude and longitude values in the DB
                        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                            $tableName,
                            'uid=' . intval($record['uid']),
                            [
                                $latitudeField => $coords['latitude'],
                                $longitudeField => $coords['longitude'],
                            ]
                        );
                    }
                }
            }
        }

        return count($records);
    }

    /**
     * fetches the city of a ZIP
     * uses the JSON query string.
     *
     * @todo: switch parameters
     */
    public function getCityFromZip($zip, $country = 'Germany', $street = null)
    {
        $results = null;

        $address = $street . ', ' . $zip . ', ' . $country;
        $address = trim($address, ', ');    // remove trailing commas and whitespaces

        if ($address) {
            $cacheObject = $this->initializeCache();

                // create the cache key
            $cacheKey = 'geocodecityfromzip-' . strtolower(str_replace(' ', '-', preg_replace('/[^0-9a-zA-Z ]/m', '', $address)));

                // not in cache yet
            if (!$cacheObject->has($cacheKey)) {
                $geocodingUrl = $this->baseApiUrl . '&address=' . urlencode($address);
                $results = GeneralUtility::getUrl($geocodingUrl);
                $results = json_decode($results);

                $results = [
                    'accuracy' => $accuracy,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];

                if ($latitude != 0) {
                    // Now store the $result in cache and return
                    $cacheObject->set($cacheKey, $results, [], $this->cacheTime);
                }
            } else {
                $results = $cacheObject->get($cacheKey);
            }
        }

        return $results;
    }

    /**
     * Initializes the cache for the DB requests.
     *
     * @return Cache Object
     */
    protected function initializeCache()
    {
        try {
            $cacheManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class);

            return $cacheManager->getCache('geocoding');
        } catch (\TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException $e) {
            throw new \RuntimeException('Unable to load Cache!', 1487138924);
        }
    }
}
