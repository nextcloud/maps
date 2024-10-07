<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @author Paul Schwörer <hello@paulschwoerer.de>
 * @copyright Julien Veyssier 2019
 * @copyright Paul Schwörer 2019
 *
 */

namespace OCA\Maps\Service;

use OC\Archive\ZIP;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class FavoritesService {

	private $l10n;
	private $dbconnection;
	private $secureRandom;

	private $currentFavorite;
	private $currentFavoritesList;
	private ?string $currentXmlTag;
	private $insideWpt;
	private $nbImported;
	private $importUserId;
	private $kmlInsidePlacemark;
	private $kmlCurrentCategory;
	private bool $linesFound = false;

	public function __construct(
		private LoggerInterface $logger,
		IL10N $l10n,
		ISecureRandom $secureRandom,
		IDBConnection $dbconnection,
	) {
		$this->l10n = $l10n;
		$this->secureRandom = $secureRandom;
		$this->dbconnection = $dbconnection;
	}

	private function db_quote_escape_string($str) {
		return $this->dbconnection->quote($str);
	}

	/**
	 * @param string $userId
	 * @param int $pruneBefore
	 * @param string|null $filterCategory
	 * @return array with favorites
	 */
	public function getFavoritesFromDB($userId, $pruneBefore = 0, $filterCategory = null, $isDeletable = true, $isUpdateable = true, $isShareable = true) {
		$favorites = [];
		$qb = $this->dbconnection->getQueryBuilder();
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
		if ($filterCategory !== null) {
			$qb->andWhere(
				$qb->expr()->eq('category', $qb->createNamedParameter($filterCategory, IQueryBuilder::PARAM_STR))
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
			$favorites[] = [
				'id' => $id,
				'name' => $name,
				'date_modified' => $date_modified,
				'date_created' => $date_created,
				'lat' => $lat,
				'lng' => $lng,
				'category' => $category,
				'comment' => $comment,
				'extensions' => $extensions,
				'isDeletable' => $isDeletable,
				//Saving maps information in the file
				'isUpdateable' => $isUpdateable,
				'isShareable' => $isShareable,
			];
		}
		$req->closeCursor();
		return $favorites;
	}

	public function getFavoriteFromDB($id, $userId = null, $category = null, $isDeletable = true, $isUpdateable = true, $isShareable = true) {
		$favorite = null;
		$qb = $this->dbconnection->getQueryBuilder();
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
		if ($category !== null) {
			$qb->andWhere(
				$qb->expr()->eq('category', $qb->createNamedParameter($category, IQueryBuilder::PARAM_STR))
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
				'extensions' => $extensions,
				'isDeletable' => $isDeletable,
				//Saving maps information in the file
				'isUpdateable' => $isUpdateable,
				'isShareable' => $isShareable,
			];
			break;
		}
		$req->closeCursor();
		return $favorite;
	}

	public function addFavoriteToDB($userId, $name, $lat, $lng, $category, $comment, $extensions) {
		$nowTimeStamp = (new \DateTime())->getTimestamp();
		$qb = $this->dbconnection->getQueryBuilder();
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
		return $favoriteId;
	}

	public function addMultipleFavoritesToDB($userId, $favoriteList) {
		$nowTimeStamp = (new \DateTime())->getTimestamp();

		$values = [];
		foreach ($favoriteList as $fav) {
			if (
				!isset($fav['lat']) or !is_numeric($fav['lat']) or
				!isset($fav['lng']) or !is_numeric($fav['lng'])
			) {
				continue;
			} else {
				$lat = floatval($fav['lat']);
				$lng = floatval($fav['lng']);
			}
			$value = '(' .
				$this->db_quote_escape_string($userId) . ', ' .
				((!isset($fav['name']) or !$fav['name']) ? 'NULL' : $this->db_quote_escape_string($fav['name'])) . ', ' .
				((!isset($fav['date_created']) or !is_numeric($fav['date_created'])) ? $this->db_quote_escape_string($nowTimeStamp) : $this->db_quote_escape_string($fav['date_created'])) . ', ' .
				$this->db_quote_escape_string($nowTimeStamp) . ', ' .
				$this->db_quote_escape_string($lat) . ', ' .
				$this->db_quote_escape_string($lng) . ', ' .
				((!isset($fav['category']) or !$fav['category']) ? 'NULL' : $this->db_quote_escape_string($fav['category'])) . ', ' .
				((!isset($fav['comment']) or !$fav['comment']) ? 'NULL' : $this->db_quote_escape_string($fav['comment'])) . ', ' .
				((!isset($fav['extensions']) or !$fav['extensions']) ? 'NULL' : $this->db_quote_escape_string($fav['extensions'])) . ')';
			array_push($values, $value);
		}
		$valuesStr = implode(', ', $values);
		$sql = '
            INSERT INTO *PREFIX*maps_favorites
            (user_id, name, date_created, date_modified, lat, lng, category, comment, extensions)
            VALUES ' . $valuesStr . ' ;';
		$req = $this->dbconnection->prepare($sql);
		$req->execute();
		$req->closeCursor();
	}

	public function renameCategoryInDB($userId, $cat, $newName) {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->update('maps_favorites');
		$qb->set('category', $qb->createNamedParameter($newName, IQueryBuilder::PARAM_STR));
		$qb->where(
			$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
		);
		$qb->andWhere(
			$qb->expr()->eq('category', $qb->createNamedParameter($cat, IQueryBuilder::PARAM_STR))
		);
		$req = $qb->execute();
	}

	public function editFavoriteInDB($id, $name, $lat, $lng, $category, $comment, $extensions) {
		$nowTimeStamp = (new \DateTime())->getTimestamp();
		$qb = $this->dbconnection->getQueryBuilder();
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
	}

	public function deleteFavoriteFromDB($id) {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->delete('maps_favorites')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
	}

	public function deleteFavoritesFromDB($ids, $userId) {
		$qb = $this->dbconnection->getQueryBuilder();
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
		} else {
			return;
		}
		$req = $qb->execute();
	}

	public function countFavorites($userId, $categoryList, $begin, $end) {
		if ($categoryList === null or
			(is_array($categoryList) and count($categoryList) === 0)
		) {
			return 0;
		}
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*) AS co'))
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
			$nbFavorites = intval($row['co']);
			break;
		}
		$req->closeCursor();

		return $nbFavorites;
	}

	/**
	 * @param $file
	 * @return array
	 * @throws \Exception
	 */
	public function getFavoritesFromJSON($file) {
		$favorites = [];

		// Decode file content from JSON
		$data = json_decode($file->getContent(), true, 512);

		$id = 0;
		// Loop over all favorite entries
		foreach ($data['features'] as $value) {
			$currentFavorite = [
				'id' => $id,
				'isDeletable' => $file->isUpdateable(),
				//Saving maps information in the file
				'isUpdateable' => $file->isUpdateable(),
				'isShareable' => false,
				'extensions' => [],
			];

			// Read geometry
			$currentFavorite['lng'] = floatval($value['geometry']['coordinates'][0]);
			$currentFavorite['lat'] = floatval($value['geometry']['coordinates'][1]);
			foreach ($value['properties'] as $key => $v) {
				if ($key === 'Title') {
					$currentFavorite['name'] = $value['properties']['Title'];
				} elseif ($key === 'Published') {
					if (!is_numeric($value['properties']['Published'])) {
						$time = new \DateTime($value['properties']['Published']);
						$time = $time->getTimestamp();
					} else {
						$time = $value['properties']['Published'];
					}
					$currentFavorite['date_created'] = $time;
				} elseif ($key === 'Updated') {
					if (!is_numeric($value['properties']['Updated'])) {
						$time = new \DateTime($value['properties']['Updated']);
						$time = $time->getTimestamp();
					} else {
						$time = $value['properties']['Updated'];
					}
					$currentFavorite['date_modified'] = $time;
				} elseif ($key === 'Category') {
					$currentFavorite['category'] = $v;
				} elseif ($key === 'Comment') {
					$currentFavorite['comment'] = $v;
				} else {
					$currentFavorite[$key] = $v;
					$currentFavorite['extensions'][$key] = $v;
				}


			}
			if (!array_key_exists('category', $currentFavorite)) {
				$currentFavorite['category'] = $this->l10n->t('Personal');
			}
			if (!array_key_exists('comment', $currentFavorite)) {
				$currentFavorite['comment'] = '';
			}
			if (
				array_key_exists('Location', $value['properties']) &&
				array_key_exists('Address', $value['properties']['Location'])
			) {
				$currentFavorite['comment'] = $currentFavorite['comment']."\n".$value['properties']['Location']['Address'];
			}

			// Store this favorite
			$favorites[] = $currentFavorite;
			$id++;
		}

		return $favorites;
	}

	public function getFavoriteFromJSON($file, $id) {
		$favorites = $this->getFavoritesFromJSON($file);
		if (array_key_exists($id, $favorites)) {
			return $favorites[$id];
		} else {
			return null;
		}
	}

	private function addFavoriteToJSONData($data, $name, $lat, $lng, $category, $comment, $extensions, $nowTimeStamp) {
		$favorite = [
			'type' => 'Feature',
			'geometry' => [
				'type' => 'Point',
				'coordinates' => [
					$lng,
					$lat
				]
			],
			'properties' => [
				'Title' => $name,
				'Category' => $category,
				'Published' => $nowTimeStamp,
				'Updated' => $nowTimeStamp,
				'Comment' => $comment,
			]
		];
		if (is_array($extensions)) {
			foreach ($extensions as $key => $value) {
				$favorite['properties'][$key] = $value;
			}
		}
		$id = array_push($data['features'], $favorite) - 1;
		return [
			'id' => $id,
			'data' => $data,
		];
	}

	public function addFavoriteToJSON($file, $name, $lat, $lng, $category, $comment, $extensions) {
		$nowTimeStamp = (new \DateTime())->getTimestamp();
		$data = json_decode($file->getContent(), true, 512);

		$tmp = $this->addFavoriteToJSONData($data, $name, $lat, $lng, $category, $comment, $extensions, $nowTimeStamp);

		$file->putContent(json_encode($tmp['data'], JSON_PRETTY_PRINT));
		return $tmp['id'];
	}

	public function addFavoritesToJSON($file, $favorites) {
		$nowTimeStamp = (new \DateTime())->getTimestamp();
		$data = json_decode($file->getContent(), true, 512);
		$ids = [];
		foreach ($favorites as $favorite) {
			$tmp = $this->addFavoriteToJSONData($data, $favorite['name'], $favorite['lat'], $favorite['lng'], $favorite['category'], $favorite['comment'], $favorite['extensions'], $nowTimeStamp);
			$ids[] = $tmp['id'];
			$data = $tmp['data'];
		}
		$file->putContent(json_encode($data, JSON_PRETTY_PRINT));
		return $ids;
	}

	public function renameCategoryInJSON($file, $cat, $newName) {
		$nowTimeStamp = (new \DateTime())->getTimestamp();
		$data = json_decode($file->getContent(), true, 512);
		$this->logger->debug($cat);
		foreach ($data['features'] as $key => $value) {
			if (!array_key_exists('Category', $value['properties'])) {
				$value['properties']['Category'] = $this->l10n->t('Personal');
				$data['features'][$key]['properties']['Category'] = $this->l10n->t('Personal');
			}
			if (array_key_exists('Category', $value['properties']) && $value['properties']['Category'] == $cat) {
				$data['features'][$key]['properties']['Category'] = $newName;
				$data['features'][$key]['properties']['Updated'] = $nowTimeStamp;
			}
		}
		$file->putContent(json_encode($data, JSON_PRETTY_PRINT));
	}

	public function editFavoriteInJSON($file, $id, $name, $lat, $lng, $category, $comment, $extensions) {
		$nowTimeStamp = (new \DateTime())->getTimestamp();
		$data = json_decode($file->getContent(), true, 512);
		$createdTimeStamp = $data['features'][$id]['properties']['Published'];
		$favorite = [
			'type' => 'Feature',
			'geometry' => [
				'type' => 'Point',
				'coordinates' => [
					$lng ?? $data['features'][$id]['geometry']['coordinates'][0],
					$lat ?? $data['features'][$id]['geometry']['coordinates'][1]
				]
			],
			'properties' => [
				'Title' => $name ?? $data['features'][$id]['properties']['Title'],
				'Category' => $category ?? $data['features'][$id]['properties']['Category'],
				'Published' => $createdTimeStamp,
				'Updated' => $nowTimeStamp,
				'Comment' => $comment ?? $data['features'][$id]['properties']['Comment'],
			]
		];
		if (is_array($extensions)) {
			foreach ($extensions as $key => $value) {
				$favorite['properties'][$key] = $value;
			}
		}

		$data['features'][$id] = $favorite;

		$file->putContent(json_encode($data, JSON_PRETTY_PRINT));
	}

	public function deleteFavoriteFromJSON($file, $id): int {
		$data = json_decode($file->getContent(), true, 512);
		$countBefore = count($data['features']);
		array_splice($data['features'], $id, 1);
		$file->putContent(json_encode($data, JSON_PRETTY_PRINT));
		return $countBefore - count($data['features']);
	}

	public function deleteFavoritesFromJSON($file, $ids) {
		$data = json_decode($file->getContent(), true, 512);
		foreach ($ids as $id) {
			array_splice($data['features'], $id, 1);
		}
		$file->putContent(json_encode($data, JSON_PRETTY_PRINT));
	}

	public function exportFavorites($userId, $fileHandler, $categoryList, $begin, $end, $appVersion) {
		$qb = $this->dbconnection->getQueryBuilder();
		$nbFavorites = $this->countFavorites($userId, $categoryList, $begin, $end);

		$gpxHeader = '<?xml version="1.0" encoding="UTF-8" standalone="yes" ?>
<gpx version="1.1" creator="Nextcloud Maps ' . $appVersion . '" xmlns="http://www.topografix.com/GPX/1/1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd">
  <metadata>
    <name>favourites</name>
  </metadata>';
		fwrite($fileHandler, $gpxHeader . "\n");

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
					$qb->expr()->gte('date_created', $qb->createNamedParameter($begin, IQueryBuilder::PARAM_INT))
				);
			}
			if ($end !== null) {
				$qb->andWhere(
					$qb->expr()->lte('date_created', $qb->createNamedParameter($end, IQueryBuilder::PARAM_INT))
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
				$name = str_replace('&', '&amp;', $row['name']);
				$epoch = $row['date_created'];
				$date = '';
				if (is_numeric($epoch)) {
					$epoch = intval($epoch);
					$dt = new \DateTime("@$epoch");
					$date = $dt->format('Y-m-d\TH:i:s\Z');
				}
				$lat = $row['lat'];
				$lng = $row['lng'];
				$category = str_replace('&', '&amp;', $row['category']);
				$comment = str_replace('&', '&amp;', $row['comment']);
				$extensions = str_replace('&', '&amp;', $row['extensions']);

				$gpxExtension = '';
				$gpxText .= '  <wpt lat="' . $lat . '" lon="' . $lng . '">' . "\n";
				$gpxText .= '   <name>' . $name . '</name>' . "\n";
				$gpxText .= '   <time>' . $date . '</time>' . "\n";
				if ($category !== null && strlen($category) > 0) {
					$gpxText .= '   <type>' . $category . '</type>' . "\n";
				} else {
					$gpxText .= '   <type>' . $this->l10n->t('Personal') . '</type>' . "\n";
				}
				if ($comment !== null && strlen($comment) > 0) {
					$gpxText .= '   <desc>' . $comment . '</desc>' . "\n";
				}
				if ($extensions !== null && strlen($extensions) > 0) {
					$gpxExtension .= '     <maps-extensions>' . $extensions . '</maps-extensions>' . "\n";
				}
				if ($gpxExtension !== '') {
					$gpxText .= '   <extensions>' . "\n" . $gpxExtension;
					$gpxText .= '   </extensions>' . "\n";
				}
				$gpxText .= '  </wpt>' . "\n";
			}
			$req->closeCursor();
			// write the chunk !
			fwrite($fileHandler, $gpxText);
			$favIndex = $favIndex + $chunkSize;
		}
		$gpxEnd = '</gpx>' . "\n";
		fwrite($fileHandler, $gpxEnd);
	}

	public function importFavorites($userId, $file) {
		$lowerFileName = strtolower($file->getName());
		if ($this->endswith($lowerFileName, '.gpx')) {
			return $this->importFavoritesFromGpx($userId, $file);
		} elseif ($this->endswith($lowerFileName, '.kml')) {
			$fp = $file->fopen('r');
			$name = $file->getName();
			return $this->importFavoritesFromKml($userId, $fp, $name);
		} elseif ($this->endswith($lowerFileName, '.kmz')) {
			return $this->importFavoritesFromKmz($userId, $file);
		} elseif ($this->endswith($lowerFileName, '.json') or $this->endswith($lowerFileName, '.geojson')) {
			return $this->importFavoritesFromGeoJSON($userId, $file);
		}
	}

	public function importFavoritesFromKmz($userId, $file) {
		$path = $file->getStorage()->getLocalFile($file->getInternalPath());
		$name = $file->getName();
		$zf = new ZIP($path);
		if (count($zf->getFiles()) > 0) {
			$zippedFilePath = $zf->getFiles()[0];
			$fstream = $zf->getStream($zippedFilePath, 'r');

			$result = $this->importFavoritesFromKml($userId, $fstream, $name);
		} else {
			$result = [
				'nbImported' => 0,
				'linesFound' => false
			];
		}
		return $result;
	}

	public function importFavoritesFromKml($userId, $fp, $name) {
		$this->nbImported = 0;
		$this->linesFound = false;
		$this->currentFavoritesList = [];
		$this->importUserId = $userId;
		$this->kmlInsidePlacemark = false;
		$this->kmlCurrentCategory = '';

		$xml_parser = xml_parser_create();
		xml_set_object($xml_parser, $this);
		xml_set_element_handler($xml_parser, 'kmlStartElement', 'kmlEndElement');
		xml_set_character_data_handler($xml_parser, 'kmlDataElement');

		// using xml_parse to be able to parse file chunks in case it's too big
		while ($data = fread($fp, 4096000)) {
			if (!xml_parse($xml_parser, $data, feof($fp))) {
				$this->logger->error(
					'Exception in ' . $name . ' parsing at line ' .
					xml_get_current_line_number($xml_parser) . ' : ' .
					xml_error_string(xml_get_error_code($xml_parser)),
					['app' => 'maps']
				);
				return 0;
			}
		}
		fclose($fp);
		xml_parser_free($xml_parser);

		return [
			'nbImported' => $this->nbImported,
			'linesFound' => $this->linesFound
		];
	}

	private function kmlStartElement($parser, $name, $attrs) {
		$this->currentXmlTag = $name;
		if ($name === 'PLACEMARK') {
			$this->currentFavorite = [];
			$this->kmlInsidePlacemark = true;
		}
		if ($name === 'LINESTRING') {
			$this->linesFound = true;
		}
	}

	private function kmlEndElement($parser, $name) {
		if ($name === 'KML') {
			// create last bunch
			if (count($this->currentFavoritesList) > 0) {
				$this->addMultipleFavoritesToDB($this->importUserId, $this->currentFavoritesList);
			}
			unset($this->currentFavoritesList);
		} elseif ($name === 'PLACEMARK') {
			$this->kmlInsidePlacemark = false;
			// store favorite
			$this->nbImported++;
			$this->currentFavorite['category'] = $this->kmlCurrentCategory;
			if (!isset($this->currentFavorite['category']) or $this->currentFavorite['category'] === '') {
				$this->currentFavorite['category'] = $this->l10n->t('Personal');
			}
			// convert date
			if (isset($this->currentFavorite['date_created'])) {
				$time = new \DateTime($this->currentFavorite['date_created']);
				$timestamp = $time->getTimestamp();
				$this->currentFavorite['date_created'] = $timestamp;
			}
			if (isset($this->currentFavorite['coordinates'])) {
				$spl = explode(',', $this->currentFavorite['coordinates']);
				if (count($spl) > 1) {
					$this->currentFavorite['lat'] = floatval($spl[1]);
					$this->currentFavorite['lng'] = floatval($spl[0]);
				}
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

	private function kmlDataElement($parser, $data) {
		$d = trim($data);
		if (!empty($d)) {
			if (!$this->kmlInsidePlacemark) {
				if ($this->currentXmlTag === 'NAME') {
					$this->kmlCurrentCategory = $this->kmlCurrentCategory . $d;
				}
			} else {
				if ($this->currentXmlTag === 'NAME') {
					$this->currentFavorite['name'] = (isset($this->currentFavorite['name'])) ? $this->currentFavorite['name'] . $d : $d;
				} elseif ($this->currentXmlTag === 'WHEN') {
					$this->currentFavorite['date_created'] = (isset($this->currentFavorite['date_created'])) ? $this->currentFavorite['date_created'] . $d : $d;
				} elseif ($this->currentXmlTag === 'COORDINATES') {
					$this->currentFavorite['coordinates'] = (isset($this->currentFavorite['coordinates'])) ? $this->currentFavorite['coordinates'] . $d : $d;
				} elseif ($this->currentXmlTag === 'DESCRIPTION') {
					$this->currentFavorite['comment'] = (isset($this->currentFavorite['comment'])) ? $this->currentFavorite['comment'] . $d : $d;
				}
			}
		}
	}

	public function importFavoritesFromGpx($userId, $file) {
		$this->nbImported = 0;
		$this->linesFound = false;
		$this->currentFavoritesList = [];
		$this->importUserId = $userId;
		$this->insideWpt = false;

		$xml_parser = xml_parser_create();
		xml_set_object($xml_parser, $this);
		xml_set_element_handler($xml_parser, 'gpxStartElement', 'gpxEndElement');
		xml_set_character_data_handler($xml_parser, 'gpxDataElement');

		$fp = $file->fopen('r');

		// using xml_parse to be able to parse file chunks in case it's too big
		while ($data = fread($fp, 4096000)) {
			if (!xml_parse($xml_parser, $data, feof($fp))) {
				$this->logger->error(
					'Exception in ' . $file->getName() . ' parsing at line ' .
					xml_get_current_line_number($xml_parser) . ' : ' .
					xml_error_string(xml_get_error_code($xml_parser)),
					['app' => 'maps']
				);
				return 0;
			}
		}
		fclose($fp);
		xml_parser_free($xml_parser);

		return [
			'nbImported' => $this->nbImported,
			'linesFound' => $this->linesFound
		];
	}

	private function gpxStartElement($parser, $name, $attrs) {
		$this->currentXmlTag = $name;
		if ($name === 'WPT') {
			$this->insideWpt = true;
			$this->currentFavorite = [];
			if (isset($attrs['LAT'])) {
				$this->currentFavorite['lat'] = floatval($attrs['LAT']);
			}
			if (isset($attrs['LON'])) {
				$this->currentFavorite['lng'] = floatval($attrs['LON']);
			}
		}
		if ($name === 'TRK' or $name === 'RTE') {
			$this->linesFound = true;
		}
	}

	private function gpxEndElement($parser, $name) {
		if ($name === 'GPX') {
			// create last bunch
			if (count($this->currentFavoritesList) > 0) {
				$this->addMultipleFavoritesToDB($this->importUserId, $this->currentFavoritesList);
			}
			unset($this->currentFavoritesList);
		} elseif ($name === 'WPT') {
			$this->insideWpt = false;
			// store favorite
			$this->nbImported++;
			// convert date
			if (isset($this->currentFavorite['date_created'])) {
				$time = new \DateTime($this->currentFavorite['date_created']);
				$timestamp = $time->getTimestamp();
				$this->currentFavorite['date_created'] = $timestamp;
			}
			if (!isset($this->currentFavorite['category']) or $this->currentFavorite['category'] === '') {
				$this->currentFavorite['category'] = $this->l10n->t('Personal');
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
			if ($this->insideWpt and $this->currentXmlTag === 'NAME') {
				$this->currentFavorite['name'] = (isset($this->currentFavorite['name'])) ? $this->currentFavorite['name'] . $d : $d;
			} elseif ($this->insideWpt and $this->currentXmlTag === 'TIME') {
				$this->currentFavorite['date_created'] = (isset($this->currentFavorite['date_created'])) ? $this->currentFavorite['date_created'] . $d : $d;
			} elseif ($this->insideWpt and $this->currentXmlTag === 'TYPE') {
				$this->currentFavorite['category'] = (isset($this->currentFavorite['category'])) ? $this->currentFavorite['category'] . $d : $d;
			} elseif ($this->insideWpt and $this->currentXmlTag === 'DESC') {
				$this->currentFavorite['comment'] = (isset($this->currentFavorite['comment'])) ? $this->currentFavorite['comment'] . $d : $d;
			} elseif ($this->insideWpt and $this->currentXmlTag === 'MAPS-EXTENSIONS') {
				$this->currentFavorite['extensions'] = (isset($this->currentFavorite['extensions'])) ? $this->currentFavorite['extensions'] . $d : $d;
			}
		}
	}

	public function importFavoritesFromGeoJSON($userId, $file) {
		$this->nbImported = 0;
		$this->linesFound = false;
		$this->currentFavoritesList = [];
		$this->importUserId = $userId;


		// Decode file content from JSON
		$data = json_decode($file->getContent(), true, 512);

		if ($data == null or !isset($data['features'])) {
			$this->logger->error(
				'Exception parsing '.$file->getName().': no places found to import',
				['app' => 'maps']
			);
		}

		// Loop over all favorite entries
		foreach ($data['features'] as $key => $value) {
			$this->currentFavorite = [];

			// Ensure that we have a valid GeoJSON Point geometry
			if ($value['geometry']['type'] !== 'Point') {
				$this->linesFound = true;
				continue;
			}

			// Read geometry
			$this->currentFavorite['lng'] = floatval($value['geometry']['coordinates'][0]);
			$this->currentFavorite['lat'] = floatval($value['geometry']['coordinates'][1]);

			$this->currentFavorite['name'] = $value['properties']['Title'];
			$this->currentFavorite['category'] = $this->l10n->t('Personal');

			$time = new \DateTime($value['properties']['Published']);
			$this->currentFavorite['date_created'] = $time->getTimestamp();

			$time = new \DateTime($value['properties']['Updated']);
			$this->currentFavorite['date_modified'] = $time->getTimestamp();

			if (isset($value['properties']['Location']['Address'])) {
				$this->currentFavorite['comment'] = $value['properties']['Location']['Address'];
			}


			// Store this favorite
			array_push($this->currentFavoritesList, $this->currentFavorite);
			$this->nbImported++;

			// if we have enough favorites, we create them and clean the array
			if (count($this->currentFavoritesList) >= 500) {
				$this->addMultipleFavoritesToDB($this->importUserId, $this->currentFavoritesList);
				unset($this->currentFavoritesList);
				$this->currentFavoritesList = [];
			}
		}

		// Store last set of favorites
		if (count($this->currentFavoritesList) > 0) {
			$this->addMultipleFavoritesToDB($this->importUserId, $this->currentFavoritesList);
		}
		unset($this->currentFavoritesList);

		return [
			'nbImported' => $this->nbImported,
			'linesFound' => $this->linesFound
		];
	}

	private function endswith($string, $test) {
		$strlen = strlen($string);
		$testlen = strlen($test);
		if ($testlen > $strlen) {
			return false;
		}
		return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
	}

}
