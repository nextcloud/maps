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
        $qb->select('id', 'name', 'date_created', 'date_modified', 'lat', 'lng', 'category', 'comment', 'extensions')
            ->from('maps_favorites', 'f')
            ->where(
                $qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
            );
        if (intval($pruneBefore) > 0) {
            $qb->andWhere(
                $qb->expr()->gt('date_modified', $qb->createNamedParameter($pruneBefore, IQueryBuilder::PARAM_INT))
            );
        }
        $req = $qb->execute();

        while ($row = $req->fetch()) {
            $id = intval($row['id']);
            $name = $row['name'];
            $date_modified = intval($row['date_modified']);
            $date_created = intval($row['date_created']);
            $lat = floatval($row['lat']);
            $lng = floatval($row['lng']);
            $category = $row['category'];
            $comment = $row['comment'];
            $extensions = $row['extensions'];
            array_push($favorites, [
                'id' => $id,
                'name' => $name,
                'date_modified' => $date_modified,
                'date_created' => $date_created,
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
        $qb->select('id', 'name', 'date_modified', 'date_created', 'lat', 'lng', 'category', 'comment', 'extensions')
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
            $date_modified = intval($row['date_modified']);
            $date_created = intval($row['date_created']);
            $lat = floatval($row['lat']);
            $lng = floatval($row['lng']);
            $category = $row['category'];
            $comment = $row['comment'];
            $extensions = $row['extensions'];
            $favorite = [
                'id' => $id,
                'name' => $name,
                'date_modified' => $date_modified,
                'date_created' => $date_created,
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
                'date_created' => $qb->createNamedParameter($nowTimeStamp, IQueryBuilder::PARAM_INT),
                'date_modified' => $qb->createNamedParameter($nowTimeStamp, IQueryBuilder::PARAM_INT),
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
        $qb->set('date_modified', $qb->createNamedParameter($nowTimeStamp, IQueryBuilder::PARAM_INT));
        if ($lat !== null) {
            $qb->set('lat', $qb->createNamedParameter($lat, IQueryBuilder::PARAM_LOB));
        }
        if ($lng !== null) {
            $qb->set('lng', $qb->createNamedParameter($lng, IQueryBuilder::PARAM_LOB));
        }
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

    public function countFavorites($userId, $categoryList, $begin, $end) {
        $qb = $this->qb;
        $qb->select($qb->createFunction('COUNT(*)'))
            ->from('maps_favorites', 'f')
            ->where(
                $qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
            );
        if ($begin !== null) {
            $qb->andWhere(
                $qb->expr()->gt('date_created', $qb->createNamedParameter($begin, IQueryBuilder::PARAM_INT))
            );
        }
        if ($end !== null) {
            $qb->andWhere(
                $qb->expr()->lt('date_created', $qb->createNamedParameter($end, IQueryBuilder::PARAM_INT))
            );
        }
        if (count($categoryList) > 0) {
            $or = $qb->expr()->orx();
            foreach ($categoryList as $cat) {
                $or->add($qb->expr()->eq('category', $qb->createNamedParameter($cat, IQueryBuilder::PARAM_STR)));
            }
            $qb->andWhere($or);
        }
        $nbFavorites = 0;
        $req = $qb->execute();
        while ($row = $req->fetch()) {
              $nbFavorites = intval($row['COUNT(*)']);
              break;
        }
        $req->closeCursor();
        $qb = $qb->resetQueryParts();

        return $nbFavorites;
    }

    public function exportFavorites($userId, $fileHandler, $categoryList, $begin, $end, $appVersion) {
        $qb = $this->qb;
        $nbFavorites = $this->countFavorites($userId, $categoryList, $begin, $end);

        $gpxHeader = '<?xml version="1.0" encoding="UTF-8" standalone="yes" ?>
<gpx version="1.1" creator="Nextcloud Maps '.$appVersion.'" xmlns="http://www.topografix.com/GPX/1/1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd">
  <metadata>
    <name>favourites</name>
  </metadata>';
        fwrite($fileHandler, $gpxHeader."\n");

        $chunkSize = 10000;
        $favIndex = 0;

        while ($favIndex < $nbFavorites) {
            $gpxText = '';

            $qb->select('id', 'name', 'date_created', 'date_modified', 'lat', 'lng', 'category', 'comment', 'extensions')
                ->from('maps_favorites', 'f')
                ->where(
                    $qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
                );
            if ($begin !== null) {
                $qb->andWhere(
                    $qb->expr()->gt('date_created', $qb->createNamedParameter($begin, IQueryBuilder::PARAM_INT))
                );
            }
            if ($end !== null) {
                $qb->andWhere(
                    $qb->expr()->lt('date_created', $qb->createNamedParameter($end, IQueryBuilder::PARAM_INT))
                );
            }
            if (count($categoryList) > 0) {
                $or = $qb->expr()->orx();
                foreach ($categoryList as $cat) {
                    $or->add($qb->expr()->eq('category', $qb->createNamedParameter($cat, IQueryBuilder::PARAM_STR)));
                }
                $qb->andWhere($or);
            }
            $qb->orderBy('date_created', 'ASC')
               ->setMaxResults($chunkSize)
               ->setFirstResult($favIndex);
            $req = $qb->execute();

            while ($row = $req->fetch()) {
                $name = $row['name'];
                $epoch = $row['date_created'];
                $date = '';
                if (is_numeric($epoch)) {
                    $epoch = intval($epoch);
                    $dt = new \DateTime("@$epoch");
                    $date = $dt->format('Y-m-d\TH:i:s\Z');
                }
                $lat = $row['lat'];
                $lng = $row['lng'];
                $category = $row['category'];
                $comment = $row['comment'];
                $extensions = $row['extensions'];

                $gpxExtension = '';
                $gpxText .= '  <wpt lat="'.$lat.'" lon="'.$lng.'">' . "\n";
                $gpxText .= '   <name>' . $name . '</name>' . "\n";
                $gpxText .= '   <time>' . $date . '</time>' . "\n";
                if ($category !== null && strlen($category) > 0) {
                    $gpxText .= '   <type>' . $category . '</type>' . "\n";
                }
                else {
                    $gpxText .= '   <type>no category</type>' . "\n";
                }
                if ($comment !== null && strlen($comment) > 0) {
                    $gpxText .= '   <desc>' . $comment . '</desc>' . "\n";
                }
                if ($extensions !== null && strlen($extensions) > 0) {
                    $gpxExtension .= '     <maps-extensions>' . $extensions . '</maps-extensions>' . "\n";
                }
                if ($gpxExtension !== '') {
                    $gpxText .= '   <extensions>'. "\n" . $gpxExtension;
                    $gpxText .= '   </extensions>' . "\n";
                }
                $gpxText .= '  </wpt>' . "\n";
            }
            $req->closeCursor();
            $qb = $qb->resetQueryParts();
            // write the chunk !
            fwrite($fileHandler, $gpxText);
            $favIndex = $favIndex + $chunkSize;
        }
        $gpxEnd .= '</gpx>' . "\n";
        fwrite($fileHandler, $gpxEnd);
    }

}
