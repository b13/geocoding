<?php

namespace B13\Geocoding\Service;

/*
 * This file is part of TYPO3 CMS-based extension "geocoding" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Calculate the radius between two addresses etc.
 */
class RadiusService
{
    /**
     * earth radius in kilometers.
     */
    protected $earthRadius = 6378.1;

    /**
     * calculates the distance in kilometers between two coordinates.
     *
     * @param array $coordinates1 an associative array with "latitude" and "longitude"
     * @param array $coordinates2 an associative array with "latitude" and "longitude"
     *
     * @return float
     */
    public function getDistance($coordinates1, $coordinates2)
    {

            // new formula, taken from here: http://snipplr.com/view.php?codeview&id=2531
        $pi80 = M_PI / 180;
        $lat1 = $coordinates1['latitude']  * $pi80;
        $lng1 = $coordinates1['longitude'] * $pi80;
        $lat2 = $coordinates2['latitude']  * $pi80;
        $lng2 = $coordinates2['longitude'] * $pi80;

        $distanceLatitude = $lat2 - $lat1;
        $distanceLongitude = $lng2 - $lng1;
        $a = sin($distanceLatitude / 2) * sin($distanceLatitude / 2) + cos($lat1) * cos($lat2) * sin($distanceLongitude / 2) * sin($distanceLongitude / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $km = $this->earthRadius * $c;

        return $km;
    }

    /**
     * fetches all records within a certain radius of given coordinates
     * see http://spinczyk.net/blog/2009/10/04/radius-search-with-google-maps-and-mysql/.
     *
     * @param array  $coordinates      an associative array with "latitude" and "longitude" keys
     * @param int    $maxDistance      the radius in kilometers
     * @param string $tableName        the DB table that should be queried
     * @param string $latitudeField    the DB field that holds the latitude coordinates
     * @param string $longitudeField   the DB field that holds the longitude coordinates
     * @param string $additionalFields additional fields to be selected from the table (uid is always selected)
     *
     * @return array
     */
    public function findAllDatabaseRecordsInRadius($coordinates, $maxDistance = 250, $tableName = 'pages', $latitudeField = 'latitude', $longitudeField = 'longitude', $additionalFields = '')
    {
        $fields = GeneralUtility::trimExplode(',', 'uid,' . $additionalFields, true);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);

        $distanceSqlCalc = 'ACOS(SIN(RADIANS(' . $queryBuilder->quoteIdentifier($latitudeField) . ')) * SIN(RADIANS(' . (float)$coordinates['latitude'] . ')) + COS(RADIANS(' . $queryBuilder->quoteIdentifier($latitudeField) . ')) * COS(RADIANS(' . (float)$coordinates['latitude'] . ')) * COS(RADIANS(' . $queryBuilder->quoteIdentifier($longitudeField) . ') - RADIANS(' . (float)$coordinates['longitude'] . '))) * ' . $this->earthRadius;

        return $queryBuilder
            ->select(...$fields)
            ->addSelectLiteral(
                $distanceSqlCalc . ' AS `distance`'
            )
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->comparison($distanceSqlCalc, ExpressionBuilder::LT, $maxDistance)
            )
            ->orderBy('distance')
            ->execute()
            ->fetchAll();
    }
}
