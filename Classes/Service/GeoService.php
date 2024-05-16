<?php

namespace B13\Geocoding\Service;

/*
 * This file is part of TYPO3 CMS-based extension "geocoding" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Calculate the geo coordinates of an address, using the google geocoding
 * API, an API key is needed, as this is a server-side process.
 */
class GeoService implements SingletonInterface
{
    /** base URL to fetch the Coordinates (Latitude, Longitude of a Address String.*/
    protected string $geocodingUrl = 'https://maps.googleapis.com/maps/api/geocode/json?language=de&sensor=false';
    protected int $cacheTime = 7776000;    // 90 days
    protected int $maxRetries = 0;

    public function __construct(
        protected readonly FrontendInterface $cache,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly RequestFactory $requestFactory
    ) {
        $geoCodingConfig = $extensionConfiguration->get('geocoding');
        // load from extension configuration
        $apiKey = $geoCodingConfig['googleApiKey'] ?? '';
        if (!empty($apiKey)) {
            $this->geocodingUrl .= '&key=' . $apiKey;
        }
        $this->maxRetries = (int)($geoCodingConfig['maxRetries'] ?? 0);
    }

    /**
     * core functionality: asks google for the coordinates of an address
     * stores known addresses in a local cache.
     *
     * @return array an array with latitude and longitude
     */
    public function getCoordinatesForAddress(?string $street = null, ?string $zip = null, ?string $city = null, ?string $country = 'Germany'): array
    {
        $addressParts = [];
        foreach ([$street, $zip . ' ' . $city, $country] as $addressPart) {
            if ($addressPart === null) {
                continue;
            }
            if (strlen(trim($addressPart)) <= 0) {
                continue;
            }
            $addressParts[] = trim($addressPart);
        }

        if ($addressParts === []) {
            return [];
        }

        $address = ltrim(implode(',', $addressParts), ',');
        if (empty($address)) {
            return [];
        }

        $cacheKey = 'geocode-' . strtolower(str_replace(' ', '-', preg_replace('/[^0-9a-zA-Z ]/m', '', $address)));

        // Found in cache? Return it.
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
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
        $this->cache->set($cacheKey, $result, [], $this->cacheTime);
        return $result;
    }

    /**
     * geocodes all missing records in a DB table and then stores the values
     * in the DB record.
     *
     * only works if your DB table has the necessary fields
     * helpful when calculating a batch of addresses and save the latitude/longitude automatically
     */
    public function calculateCoordinatesForAllRecordsInTable(
        string $tableName,
        string $latitudeField = 'latitude',
        string $longitudeField = 'longitude',
        string $streetField = 'street',
        string $zipField = 'zip',
        string $cityField = 'city',
        string $countryField = 'country',
        string $addWhereClause = ''
    ): int {
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

        $records = $queryBuilder->executeQuery()->fetchAllAssociative();

        foreach ($records as $record) {
            $country = $record[$countryField] ?? '';
            // resolve the label for the country
            if (($GLOBALS['TCA'][$tableName]['columns'][$countryField]['config']['type'] ?? '') === 'select') {
                foreach ($GLOBALS['TCA'][$tableName]['columns'][$countryField]['config']['items'] ?? [] as $itm) {
                    if (($itm[1] ?? null) === $country) {
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
                $coords = $this->getCoordinatesForAddress($record[$streetField] ?? null, $record[$zipField] ?? null, $record[$cityField] ?? null, $country);
                if ($coords) {
                    // Update the record to fill in the latitude and longitude values in the DB
                    $connection->update(
                        $tableName,
                        [
                            $latitudeField => $coords['latitude'],
                            $longitudeField => $coords['longitude'],
                        ],
                        [
                            'uid' => $record['uid'],
                        ]
                    );
                }
            }
        }

        return count($records);
    }

    protected function getApiCallResult(string $url, int $remainingTries = 10): array
    {
        $response = $this->requestFactory->request($url);

        $result = json_decode($response->getBody()->getContents(), true) ?? [];
        if (!in_array(($result['status'] ?? ''), ['OK', 'OVER_QUERY_LIMIT'], true)) {
            throw new \RuntimeException(
                sprintf(
                    'Request to Google Maps API returned status "%s". Got following error message: "%s"',
                    $result['status'] ?? 0,
                    $result['error_message'] ?? ''
                ),
                1621512170
            );
        }

        if (($result['status'] ?? '') === 'OVER_QUERY_LIMIT' || $remainingTries <= 0) {
            return $result;
        }
        return $this->getApiCallResult($url, $remainingTries - 1);
    }
}
