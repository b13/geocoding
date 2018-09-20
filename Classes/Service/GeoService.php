<?php

namespace B13\Geocoding\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2018 Benjamin Mack, b:dreizehn, Germany <benjamin.mack@b13.de>
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

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Calculate the geo coordinates of an address, using the googe geocoding
 * API, an API key is needed, as this is a server-side process.
 */
class GeoService
{
    /**
     * @var int
     */
    protected $cacheTime = 7776000;    // 90 days

    /**
     * @var int
     */
    protected $maxRetries = 0;

    /**
     * base URL to fetch the Coordinates (Latitude, Longitutde of a Address String.
     */
    protected $geocodingUrl = 'https://maps.googleapis.com/maps/api/geocode/json?language=de&sensor=false';

    /**
     * constructor method.
     *
     * sets the google code API key
     *
     * @param string $apiKey (optional) the API key from google, if empty, the default from the configuration is taken
     */
    public function __construct($apiKey = null)
    {
        $geoCodingConfig = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['geocoding'] ?: unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['geocoding']);
        // load from extension configuration
        if ($apiKey === null) {
            $apiKey = $geoCodingConfig['googleApiKey'] ?: '';
        }
        if (!empty($apiKey)) {
            $this->geocodingUrl .= '&key=' . $apiKey;
        }
        $this->maxRetries = (int)$geoCodingConfig['maxRetries'];
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
     * @return array an array with latitude and longitude
     */
    public function getCoordinatesForAddress($street = null, $zip = null, $city = null, $country = 'Germany'): array
    {
        $addressParts = [];
        foreach ([$street, $zip . ' ' . $city, $country] as $addressPart) {
            if (empty($addressPart)) {
                continue;
            }
            $addressParts[] = trim($addressPart);
        }

        $address = ltrim(implode(',', $addressParts), ',');
        if (empty($address)) {
            return [];
        }

        $cacheObject = $this->initializeCache();
        $cacheKey = 'geocode-' . strtolower(str_replace(' ', '-', preg_replace('/[^0-9a-zA-Z ]/m', '', $address)));

        // Found in cache? Return it.
        if ($cacheObject->has($cacheKey)) {
            return $cacheObject->get($cacheKey);
        }

        $result = $this->getApiCallResult(
            $this->geocodingUrl . '&address=' . urlencode($address),
            $this->maxRetries
        );

        if (empty($result['results']) || empty($result['results'][0]['geometry'])) {
            return [];
        }
        $geometry = $result['results'][0]['geometry'];
        $result = [
            'latitude' => $geometry['location']['lat'],
            'longitude' => $geometry['location']['lng'],
        ];
        // Now store the $result in cache and return
        $cacheObject->set($cacheKey, $result, [], $this->cacheTime);
        return $result;
    }

    /**
     * geocodes all missing records in a DB table and then stores the values
     * in the DB record.
     *
     * only works if your DB table has the necessary fields
     * helpful when calculating a batch of addresses and save the latitude/longitude automatically
     * @param string $tableName
     * @param string $latitudeField
     * @param string $longitudeField
     * @param string $streetField
     * @param string $zipField
     * @param string $cityField
     * @param string $countryField
     * @param string $addWhereClause
     * @return int
     */
    public function calculateCoordinatesForAllRecordsInTable(
        $tableName,
        $latitudeField = 'latitude',
        $longitudeField = 'longitude',
        $streetField = 'street',
        $zipField = 'zip',
        $cityField = 'city',
        $countryField = 'country',
        $addWhereClause = ''
    ): int
    {
        // Fetch all records without latitude/longitude
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder
            ->select('*')
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull($latitudeField),
                    $queryBuilder->expr()->eq($latitudeField, $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq($latitudeField, 0.00000000000),
                    $queryBuilder->expr()->isNull($longitudeField),
                    $queryBuilder->expr()->eq($longitudeField, $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq($longitudeField, 0.00000000000)
                )
            )
            ->setMaxResults(500);

        if (!empty($addWhereClause)) {
            $queryBuilder->andWhere(QueryHelper::stripLogicalOperatorPrefix($addWhereClause));
        }

        $records = $queryBuilder->execute()->fetchAll();

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
                        // Update the record to fill in the latitude and longitude values in the DB
                        $connection->update(
                            $tableName,
                            [
                                $latitudeField => $coords['latitude'],
                                $longitudeField => $coords['longitude'],
                            ],
                            [
                                'uid' => $record['uid']
                            ]
                        );
                    }
                }
            }
        }

        return count($records);
    }

    /**
     * Initializes the cache for the DB requests.
     *
     * @return FrontendInterface Cache Object
     */
    protected function initializeCache(): FrontendInterface
    {
        try {
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            return $cacheManager->getCache('geocoding');
        } catch (\TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException $e) {
            throw new \RuntimeException('Unable to load Cache!', 1487138924);
        }
    }

    /**
     * @param string $url
     * @param int $remainingTries
     * @return array
     */
    protected function getApiCallResult(string $url, int $remainingTries = 10): array
    {
        $response = GeneralUtility::getUrl($url);
        $result = json_decode($response, true);
        if ($result['status'] !== 'OVER_QUERY_LIMIT' || $remainingTries <= 0) {
            return $result;
        }
        return $this->getApiCallResult($url, $remainingTries - 1);
    }
}
