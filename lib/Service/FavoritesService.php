<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Maps\Service;

use OCP\IL10N;
use OCP\ILogger;
use OCP\DB\QueryBuilder\IQueryBuilder;

class FavoritesService {

    private $l10n;
    private $logger;
    private $qb;

    public function __construct (ILogger $logger, IL10N $l10n) {
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();
    }

    /**
     * @param string $userId
     * @param int $pruneBefore
     * @return array with favorites
     */
     public function getFavoritesFromDB($userId, $pruneBefore=0) {
         $favorites = [];
         $qb = $this->qb;
         $qb->select('id', 'name', 'timestamp', 'lat', 'lng', 'category', 'comment', 'extensions')
             ->from('maps_favorites', 'f')
             ->andWhere(
                 $qb->expr()->eq('userid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
             );
         if (intval($pruneBefore) > 0) {
             $qb->andWhere(
                 $qb->expr()->gt('timestamp', $qb->createNamedParameter($pruneBefore, IQueryBuilder::PARAM_INT))
             );
         }
         $req = $qb->execute();

         while ($row = $req->fetch()) {
             $id = intval($row['id']);
             $name = $row['name'];
             $timestamp = intval($row['timestamp']);
             $lat = floatval($row['lat']);
             $lng = floatval($row['lng']);
             $category = $row['category'];
             $comment = $row['comment'];
             $extensions = $row['extensions'];
             array_push($favorites, [
                 'id' => $id,
                 'name' => $name,
                 'timestamp' => $timestamp,
                 'lat' => $lat,
                 'lng' => $lng,
                 'category' => $category,
                 'comment' => $comment,
                 'extensions' => $extensions
             ]);
         }
         $req->closeCursor();
         $qb = $qb->resetQueryParts();
         return $favorites;
     }

}
