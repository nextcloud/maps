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
            ->where(
                $qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
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

    public function getFavoriteFromDB($id, $userId=null) {
        $favorite = null;
        $qb = $this->qb;
        $qb->select('id', 'name', 'timestamp', 'lat', 'lng', 'category', 'comment', 'extensions')
            ->from('maps_favorites', 'f')
            ->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );
        if ($userId !== null) {
            $qb->andWhere(
                $qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
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
            $favorite = [
                'id' => $id,
                'name' => $name,
                'timestamp' => $timestamp,
                'lat' => $lat,
                'lng' => $lng,
                'category' => $category,
                'comment' => $comment,
                'extensions' => $extensions
            ];
            break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        return $favorite;
    }

    public function addFavoriteToDB($userId, $name, $lat, $lng, $category, $comment, $extensions) {
        $nowTimeStamp = (new \DateTime())->getTimestamp();
        $qb = $this->qb;
        $qb->insert('maps_favorites')
            ->values([
                'user_id' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
                'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
                'timestamp' => $qb->createNamedParameter($nowTimeStamp, IQueryBuilder::PARAM_INT),
                'lat' => $qb->createNamedParameter($lat, IQueryBuilder::PARAM_LOB),
                'lng' => $qb->createNamedParameter($lng, IQueryBuilder::PARAM_LOB),
                'category' => $qb->createNamedParameter($category, IQueryBuilder::PARAM_STR),
                'comment' => $qb->createNamedParameter($comment, IQueryBuilder::PARAM_STR),
                'extensions' => $qb->createNamedParameter($extensions, IQueryBuilder::PARAM_STR)
            ]);
        $req = $qb->execute();
        $favoriteId = $qb->getLastInsertId();
        $qb = $qb->resetQueryParts();
        return $favoriteId;
    }

    public function editFavoriteInDB($id, $name, $lat, $lng, $category, $comment, $extensions) {
        $nowTimeStamp = (new \DateTime())->getTimestamp();
        $qb = $this->qb;
        $qb->update('maps_favorites');
        $qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
        $qb->set('timestamp', $qb->createNamedParameter($nowTimeStamp, IQueryBuilder::PARAM_INT));
        $qb->set('lat', $qb->createNamedParameter($lat, IQueryBuilder::PARAM_LOB));
        $qb->set('lng', $qb->createNamedParameter($lng, IQueryBuilder::PARAM_LOB));
        if ($category !== null) {
            $qb->set('category', $qb->createNamedParameter($category, IQueryBuilder::PARAM_STR));
        }
        if ($comment !== null) {
            $qb->set('comment', $qb->createNamedParameter($comment, IQueryBuilder::PARAM_STR));
        }
        if ($extensions !== null) {
            $qb->set('extensions', $qb->createNamedParameter($extensions, IQueryBuilder::PARAM_STR));
        }
        $qb->where(
            $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
        );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();
    }

    public function deleteFavoriteFromDB($id) {
        $qb = $this->qb;
        $qb->delete('maps_favorites')
            ->where(
                $qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();
    }

}
