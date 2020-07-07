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
use OCP\IServerContainer;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;
use OCP\Share\IManager;
use OCP\Files\Folder;
use OCP\Files\Node;

class MyMapsService {

    private $logger;
    private $userId;

    public function __construct (ILogger $logger,
                                 IServerContainer $serverContainer,
                                 $UserId) {
        $this->logger = $logger;
        if ($UserId !== '' and $UserId !== null and $serverContainer !== null){
            $this->userfolder = $serverContainer->getUserFolder($UserId);
        }
        $this->userId = $UserId;
    }

    public function addMyMap($newName) {
        $MapData = [
            'name' => $newName,
        ];
        if (!$this->userfolder->nodeExists('/Maps')) {
            $this->userfolder->newFolder('Maps');
        }
        if ($this->userfolder->nodeExists('/Maps')) {
            $mapsFolder = $this->userfolder->get('/Maps');
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
        $mapFolder = $mapsFolder->newFolder($newName);
        $MapData['id'] = $mapFolder->getId();
        $mapFolder->newFile(".maps","{}");
        return $MapData;
    }

    public function getAllMyMaps(){
        $MyMaps = [];
        $MyMapsNodes = $this->userfolder->search('.maps');
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
                $MyMap = [
                    "id"=>$node->getParent()->getId(),
                    "name"=>$name,
                    "color"=>$color,
                    "path"=>$this->userfolder->getRelativePath($node->getParent()->getPath())
                ];
                array_push($MyMaps, $MyMap);
            }
        }
        return $MyMaps;
    }

    public function updateMyMap($id, $values) {
        $folders = $this->userfolder->getById($id);
        $folder = array_shift($folders);
        try {
            $file=$folder->get(".maps");
        } catch (NotFoundException $e) {
            $file=$folder->newFile(".maps", $content = '{}');
        }
        $mapData = json_decode($file->getContent(),true);
        foreach ($values as $key=>$value) {
            if ($key === 'newName') {
                $key = 'name';
            }
            if (is_null($value)) {
                unset($mapData[$key]);
            } else {
                $mapData[$key] = $value;
            }
        }
        $file->putContent(json_encode($mapData,JSON_PRETTY_PRINT));
        return $mapData;
    }

    public function deleteMyMap($id) {
        $folders = $this->userfolder->getById($id);
        $folder = array_shift($folders);
        if ($this->userfolder->nodeExists('/Maps')) {
            $mapsFolder = $this->userfolder->get('/Maps');
            if ($folder->getParent()->getId() === $mapsFolder->getId() ) {
                try {
                    $folder->delete();
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
