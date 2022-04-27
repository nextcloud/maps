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

use OC\OCS\Exception;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\NotFoundException;
use OCP\Files\Search\ISearchComparison;
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

    public function __construct (ILogger $logger, IRootFolder $root) {
        $this->logger = $logger;
		$this->root = $root;
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
        $MapData = [
            'name' => $folderName,
        ];
        $mapFolder = $mapsFolder->newFolder($folderName);
        $MapData['id'] = $mapFolder->getId();
        $mapFolder->newFile(".maps","{}");
        return $MapData;
    }

    public function getAllMyMaps($userId){
		$userFolder = $this->root->getUserFolder($userId);
        $MyMaps = [];
        $MyMapsNodes = $userFolder->search('.maps');
        foreach($MyMapsNodes as $node) {
            if ($node->getType() === FileInfo::TYPE_FILE and $node->getName() === ".maps") {
                $mapData = json_decode($node->getContent(), true);
                if (isset($mapData["name"])){
                    $name = $mapData["name"];
                } else {
                    $name = $node->getParent()->getName();
                }
                $color = null;
                if (isset($mapData["color"])){
                    $color = $mapData["color"];
                }
				$parentNode = $node->getParent();
				$isRoot = $parentNode->getPath() === $userFolder->getPath();
                $MyMap = [
                    "id"=>$parentNode->getId(),
                    "name"=>$name,
                    "color"=>$color,
                    "path"=>$userFolder->getRelativePath($parentNode->getPath()),
					"shareable"=>$parentNode->isShareable(),
					"deletable"=>$parentNode->isDeletable(),
					"creatable"=>$parentNode->isCreatable(),
					"updatable"=>$parentNode->isUpdateable(),
					"readable"=>$parentNode->isReadable(),
					"fileInfo"=>[
						"id" => $parentNode->getId(),
						"name" => "",
						"basename" => $isRoot ? '' : $parentNode->getName(),
						"filename" => $userFolder->getRelativePath($parentNode->getPath()),
						"etag" => $parentNode->getEtag(),
		                "permissions" => $parentNode->getPermissions(),
						"type" => $parentNode->getType(),
						"mime" => $parentNode->getMimetype(),
						"lastmod" => $parentNode->getMTime(),
						"path"=>$userFolder->getRelativePath($parentNode->getPath()),
						"sharePermissions"=>$parentNode->getPermissions(),
					]
				];
                array_push($MyMaps, $MyMap);
            }
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
