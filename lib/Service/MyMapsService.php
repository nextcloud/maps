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

use OC\Files\Search\SearchBinaryOperator;
use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchQuery;
use OC\OCS\Exception;
use OC\User\NoUserException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotPermittedException;
use OCP\Files\Search\ISearchQuery;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\NotFoundException;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;
use OCP\ICacheFactory;
use OCP\IL10N;
use OCP\ILogger;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;
use OCP\Share\IManager;
use OCP\Files\Folder;
use OCP\Files\Node;

class MyMapsService {

    private $logger;
	private $root;

    public function __construct (ILogger $logger, IRootFolder $root, ICacheFactory $cacheFactory) {
        $this->logger = $logger;
		$this->root = $root;
		$this->cacheFactory = $cacheFactory;
		$this->myMapsPathsCache = $this->cacheFactory->createDistributed('maps:myMaps-paths');
    }

    public function addMyMap($newName, $userId, $counter=0) {
		$userFolder = $this->root->getUserFolder($userId);
        if (!$userFolder->nodeExists('/Maps')) {
			$userFolder->newFolder('Maps');
        }
        if ($userFolder->nodeExists('/Maps')) {
            $mapsFolder = $userFolder->get('/Maps');
            if ($mapsFolder->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
                $response = '/Maps is not a directory';
                return $response;
            }
            else if (!$mapsFolder->isCreatable()) {
                $response = '/Maps is not writeable';
                return $response;
            }
        }
        else {
            $response = 'Impossible to create /Maps';
            return $response;
        }
        if ($counter>0) {
            $folderName = $newName." ".$counter;
        }
        else {
            $folderName = $newName;
        }

        if ($mapsFolder->nodeExists($folderName)) {
            return $this->addMyMap($newName, $userId,$counter+1);
        }
        $mapFolder = $mapsFolder->newFolder($folderName);
        $mapFolder->newFile(".maps","{}");
		$isRoot = $mapFolder->getPath() === $userFolder->getPath();
		$MyMap = [
			"id"=>$mapFolder->getId(),
			"name"=>$folderName,
			"color"=>null,
			"path"=>$userFolder->getRelativePath($mapFolder->getPath()),
			"isShareable"=>$mapFolder->isShareable(),
			"isDeletable"=>$mapFolder->isDeletable(),
			"isCreatable"=>$mapFolder->isCreatable(),
			"isUpdateable"=>$mapFolder->isUpdateable(),
			"isReadable"=>$mapFolder->isReadable(),
			"fileInfo"=>[
				"id" => $mapFolder->getId(),
				"name" => "",
				"basename" => $isRoot ? '' : $mapFolder->getName(),
				"filename" => $userFolder->getRelativePath($mapFolder->getPath()),
				"etag" => $mapFolder->getEtag(),
				"permissions" => $mapFolder->getPermissions(),
				"type" => $mapFolder->getType(),
				"mime" => $mapFolder->getMimetype(),
				"lastmod" => $mapFolder->getMTime(),
				"path"=>$userFolder->getRelativePath($mapFolder->getPath()),
				"sharePermissions"=>$mapFolder->getPermissions(),
			]
		];
		$MyMaps = $this->myMapsPathsCache->get($userId);
		if ($MyMaps !== null) {
			$MyMaps[] = $MyMap;
			$this->myMapsPathsCache->set($userId, $MyMaps, 60 * 60 * 24 );
		}
        return $MyMap;
    }

	private function updateMyMapsCache($userId): array{
		$userFolder = $this->root->getUserFolder($userId);
		$MyMaps = [];
		$MyMapsNodes = $userFolder->search(new SearchQuery(
			new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'name', '.maps'),
			0, 0, []));

		foreach ($MyMapsNodes as $node) {
			if ($node->getType() === FileInfo::TYPE_FILE and $node->getName() === ".maps") {
				$MyMaps[] = $this->node2MyMap($node, $userFolder);
			}
		}
		$this->myMapsPathsCache->set($userId, $MyMaps, 60 * 60 * 24);
		return $MyMaps;
	}

	private function node2MyMap($node, $userFolder):array{
		$mapData = json_decode($node->getContent(), true);
		if (isset($mapData["name"])) {
			$name = $mapData["name"];
		} else {
			$name = $node->getParent()->getName();
		}
		$color = null;
		if (isset($mapData["color"])) {
			$color = $mapData["color"];
		}
		$parentNode = $node->getParent();
		$isRoot = $parentNode->getPath() === $userFolder->getPath();
		$MyMap = [
			"id" => $parentNode->getId(),
			"name" => $name,
			"color" => $color,
			"path" => $userFolder->getRelativePath($parentNode->getPath()),
			"isShareable" => $parentNode->isShareable(),
			"isDeletable" => $parentNode->isDeletable(),
			"isCreatable" => $parentNode->isCreatable(),
			"isUpdateable" => $parentNode->isUpdateable(),
			"isReadable" => $parentNode->isReadable(),
			"fileInfo" => [
				"id" => $parentNode->getId(),
				"name" => "",
				"basename" => $isRoot ? '' : $parentNode->getName(),
				"filename" => $userFolder->getRelativePath($parentNode->getPath()),
				"etag" => $parentNode->getEtag(),
				"permissions" => $parentNode->getPermissions(),
				"type" => $parentNode->getType(),
				"mime" => $parentNode->getMimetype(),
				"lastmod" => $parentNode->getMTime(),
				"path" => $userFolder->getRelativePath($parentNode->getPath()),
				"sharePermissions" => $parentNode->getPermissions(),
			]
		];
		return $MyMap;
	}

    public function getAllMyMaps($userId){

        $MyMaps = [];
		try {
			$MyMaps = $this->myMapsPathsCache->get($userId);
			if ($MyMaps === null) {
				$MyMaps = $this->updateMyMapsCache($userId);
			}
		} catch (InvalidPathException | NotFoundException | NotPermittedException | NoUserException $e) {
			$this->logger->error($e->getMessage());
		}
        return $MyMaps;
    }

    public function updateMyMap($id, $values, $userId) {
		$userFolder = $this->root->getUserFolder($userId);
        $folders = $userFolder->getById($id);
        $folder = array_shift($folders);
        try {
            $file=$folder->get(".maps");
        } catch (NotFoundException $e) {
            $file=$folder->newFile(".maps", $content = '{}');
        }
        $mapData = json_decode($file->getContent(),true);
        $renamed = false;
        foreach ($values as $key=>$value) {
            if ($key === 'newName') {
                $key = 'name';
                $newName = $value;
                $renamed = true;
            }
            if (is_null($value)) {
                unset($mapData[$key]);
            } else {
                $mapData[$key] = $value;
            }
        }
        $file->putContent(json_encode($mapData,JSON_PRETTY_PRINT));
		$MyMaps = $this->myMapsPathsCache->get($userId);
		if ($MyMaps !== null) {
			$MyMap = $this->node2MyMap($file, $userFolder);
			$oldKey = array_key_first(array_filter($MyMaps, function ($m) use ($id) {
				return $m['id']===$id;
			}));
			$MyMaps[$oldKey] = $MyMap;
			$this->myMapsPathsCache->set($userId, $MyMaps, 60 * 60 * 24);
		}
        if ($renamed) {
            if ($userFolder->nodeExists('/Maps')) {
                $mapsFolder = $userFolder->get('/Maps');
                if ($folder->getParent()->getId() === $mapsFolder->getId() ) {
                    try {
                        $folder->move($mapsFolder->getPath()."/".$newName);
                    } catch (Exception $e) {
                    }
                }
            }
        }
        return $mapData;
    }

    public function deleteMyMap($id, $userId) {
		$userFolder = $this->root->getUserFolder($userId);
		$MyMaps = $this->myMapsPathsCache->get($userId);
		if ($MyMaps !== null) {
			$oldKey = array_key_first(array_filter($MyMaps, function ($m) use ($id) {
				return $m['id']===$id;
			}));
			unset($MyMaps[$oldKey]);
			$this->myMapsPathsCache->set($userId, $MyMaps, 60 * 60 * 24);
		}

        $folders = $userFolder->getById($id);
        $folder = array_shift($folders);
        if ($userFolder->nodeExists('/Maps')) {
            $mapsFolder = $userFolder->get('/Maps');
            if ($folder->getParent()->getId() === $mapsFolder->getId() ) {
                try {
                    $folder->delete();
                } catch (Exception $e) {
                    return 1;
                }
            } else {
				try {
					$file = $folder->get('.maps');
					$file->delete();
				} catch (Exception $e) {
					return 1;
				}
			}
        }
        try {
            $file=$folder->get(".maps");
            $file->delete();
        } catch (NotFoundException $e) {
            return 1;
        }
        return 0;
    }

}
