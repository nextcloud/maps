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

    private $currentFavorite;
    private $currentFavoritesList;
    private $nbImported;
    private $importUserId;

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
                'lat' => $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR),
                'lng' => $qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR),
                'category' => $qb->createNamedParameter($category, IQueryBuilder::PARAM_STR),
                'comment' => $qb->createNamedParameter($comment, IQueryBuilder::PARAM_STR),
                'extensions' => $qb->createNamedParameter($extensions, IQueryBuilder::PARAM_STR)
            ]);
        $req = $qb->execute();
        $favoriteId = $qb->getLastInsertId();
        $qb = $qb->resetQueryParts();
        return $favoriteId;
    }

    public function addMultipleFavoritesToDB($userId, $favoriteList) {
        $nowTimeStamp = (new \DateTime())->getTimestamp();
        $qb = $this->qb;

        $values = [];
        foreach ($favoriteList as $fav) {
            $name = (!array_key_exists('name', $fav) or !$fav['name']) ? null : $fav['name'];
            $ts = (!array_key_exists('date_created', $fav) or !is_numeric($fav['date_created'])) ? $nowTimeStamp : $fav['date_created'];
            if (
                !array_key_exists('lat', $fav) or !is_numeric($fav['lat']) or
                !array_key_exists('lng', $fav) or !is_numeric($fav['lng'])
            ) {
                continue;
            }
            else {
                $lat = floatval($fav['lat']);
                $lng = floatval($fav['lng']);
            }
            $category = (!array_key_exists('category', $fav) or !$fav['category']) ? null : $fav['category'];
            $comment = (!array_key_exists('comment', $fav) or !$fav['comment']) ? null : $fav['comment'];
            $extensions = (!array_key_exists('extensions', $fav) or !$fav['extensions']) ? null : $fav['extensions'];
            array_push($values, [
                'user_id' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
                'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
                'date_created' => $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT),
                'date_modified' => $qb->createNamedParameter($nowTimeStamp, IQueryBuilder::PARAM_INT),
                'lat' => $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR),
                'lng' => $qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR),
                'category' => $qb->createNamedParameter($category, IQueryBuilder::PARAM_STR),
                'comment' => $qb->createNamedParameter($comment, IQueryBuilder::PARAM_STR),
                'extensions' => $qb->createNamedParameter($extensions, IQueryBuilder::PARAM_STR)
            ]);
        }
        foreach ($values as $v) {
            $qb->insert('maps_favorites');
            $qb->values($v);
            // TODO make one request
            $req = $qb->execute();
            $qb = $qb->resetQueryParts();
        }
    }

    public function renameCategoryInDB($userId, $cat, $newName) {
        $qb = $this->qb;
        $qb->update('maps_favorites');
        $qb->set('category', $qb->createNamedParameter($newName, IQueryBuilder::PARAM_STR));
        $qb->where(
            $qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
        );
        $qb->andWhere(
            $qb->expr()->eq('category', $qb->createNamedParameter($cat, IQueryBuilder::PARAM_STR))
        );
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();
    }

    public function editFavoriteInDB($id, $name, $lat, $lng, $category, $comment, $extensions) {
        $nowTimeStamp = (new \DateTime())->getTimestamp();
        $qb = $this->qb;
        $qb->update('maps_favorites');
        $qb->set('date_modified', $qb->createNamedParameter($nowTimeStamp, IQueryBuilder::PARAM_INT));
        if ($name !== null) {
            $qb->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR));
        }
        if ($lat !== null) {
            $qb->set('lat', $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR));
        }
        if ($lng !== null) {
            $qb->set('lng', $qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR));
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

    public function deleteFavoritesFromDB($ids, $userId) {
        $qb = $this->qb;
        $qb->delete('maps_favorites')
            ->where(
                $qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
            );
        if (count($ids) > 0) {
            $or = $qb->expr()->orx();
            foreach ($ids as $id) {
                $or->add($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
            }
            $qb->andWhere($or);
        }
        else {
            return;
        }
        $req = $qb->execute();
        $qb = $qb->resetQueryParts();
    }

    public function countFavorites($userId, $categoryList, $begin, $end) {
        if ($categoryList === null or
            (is_array($categoryList) and count($categoryList) === 0)
        ) {
            return 0;
        }
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
        // apply category restrictions if it's a non-empty array
        if (!is_string($categoryList) and
            is_array($categoryList) and
            count($categoryList) > 0
        ) {
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
            // apply category restrictions if it's a non-empty array
            if (!is_string($categoryList) and
                is_array($categoryList) and
                count($categoryList) > 0
            ) {
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
        $gpxEnd = '</gpx>' . "\n";
        fwrite($fileHandler, $gpxEnd);
    }

    public function importFavorites($userId, $file) {
        $this->nbImported = 0;
        $this->currentFavoritesList = [];
        $this->importUserId = $userId;

        $xml_parser = xml_parser_create();
        xml_set_object($xml_parser, $this);
        xml_set_element_handler($xml_parser, 'gpxStartElement', 'gpxEndElement');
        xml_set_character_data_handler($xml_parser, 'gpxDataElement');

        $fp = $file->fopen('r');

        while ($data = fread($fp, 4096000)) {
            if (!xml_parse($xml_parser, $data, feof($fp))) {
                $this->logger->error(
                    'Exception in '.$file->getName().' parsing at line '.
                      xml_get_current_line_number($xml_parser).' : '.
                      xml_error_string(xml_get_error_code($xml_parser)),
                    array('app' => 'maps')
                );
                return 5;
            }
        }
        fclose($fp);
        xml_parser_free($xml_parser);

        return $this->nbImported;
    }

    private function gpxStartElement($parser, $name, $attrs) {
        //$points, array($lat, $lon, $ele, $timestamp, $acc, $bat, $sat, $ua, $speed, $bearing)
        $this->currentXmlTag = $name;
        if ($name === 'WPT') {
            $this->currentFavorite = [];
            if (array_key_exists('LAT', $attrs)) {
                $this->currentFavorite['lat'] = floatval($attrs['LAT']);
            }
            if (array_key_exists('LON', $attrs)) {
                $this->currentFavorite['lng'] = floatval($attrs['LON']);
            }
        }
        //var_dump($attrs);
    }

    private function gpxEndElement($parser, $name) {
        if ($name === 'GPX') {
            // create last bunch
            if (count($this->currentFavoritesList) > 0) {
                $this->addMultipleFavoritesToDB($this->importUserId, $this->currentFavoritesList);
            }
            unset($this->currentFavoritesList);
        }
        else if ($name === 'WPT') {
            // store favorite
            $this->nbImported++;
            // convert date
            if (array_key_exists('date_created', $this->currentFavorite)) {
                $time = new \DateTime($this->currentFavorite['date_created']);
                $timestamp = $time->getTimestamp();
                $this->currentFavorite['date_created'] = $timestamp;
            }
            if (array_key_exists('category', $this->currentFavorite)) {
                $this->currentFavorite['category'] = str_replace('no category', '', $this->currentFavorite['category']);
            }
            array_push($this->currentFavoritesList, $this->currentFavorite);
            // if we have enough favorites, we create them and clean the array
            if (count($this->currentFavoritesList) >= 500) {
                $this->addMultipleFavoritesToDB($this->importUserId, $this->currentFavoritesList);
                unset($this->currentFavoritesList);
                $this->currentFavoritesList = [];
            }
        }
    }

    private function gpxDataElement($parser, $data) {
        $d = trim($data);
        if (!empty($d)) {
            if ($this->currentXmlTag === 'NAME') {
                $this->currentFavorite['name'] = (array_key_exists('name', $this->currentFavorite)) ? $this->currentFavorite['name'].$d : $d;
            }
            else if ($this->currentXmlTag === 'TIME') {
                $this->currentFavorite['date_created'] = (array_key_exists('date_created', $this->currentFavorite)) ? $this->currentFavorite['date_created'].$d : $d;
            }
            else if ($this->currentXmlTag === 'TYPE') {
                $this->currentFavorite['category'] = (array_key_exists('category', $this->currentFavorite)) ? $this->currentFavorite['category'].$d : $d;
            }
            else if ($this->currentXmlTag === 'DESC') {
                $this->currentFavorite['comment'] = (array_key_exists('comment', $this->currentFavorite)) ? $this->currentFavorite['comment'].$d : $d;
            }
            else if ($this->currentXmlTag === 'MAPS-EXTENSIONS') {
                $this->currentFavorite['extensions'] = (array_key_exists('extensions', $this->currentFavorite)) ? $this->currentFavorite['extensions'].$d : $d;
            }
        }
    }

}
