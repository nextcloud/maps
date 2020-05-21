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

use OCP\Files\Search\ISearchComparison;
use OCP\IL10N;
use OCP\ILogger;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;
use OCP\Share\IManager;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\Search\ISearchQuery;

class MyMapsService {

    private $logger;
    private $userId;

    public function __construct (ILogger $logger, $userfolder, $userId) {
        $this->logger = $logger;
        $this->userfolder = $userfolder;
        $this->userId = $userId;
    }

    public function getAllMyMaps(){
        $MyMaps = [];
        $MyMapsNodes = $this->userfolder->search('.maps');
        foreach($MyMapsNodes as $node) {
            $this->logger->debug($node->getPath());
            if ($node->getType() === FileInfo::TYPE_FILE and $node->getName() === ".maps") {
                $MapData = json_decode($node->getContent());
                if (array_key_exists("name", $MapData)){
                    $name = $MapData["name"];
                } else {
                    $name = $node->getParent()->getName();
                }
                $color = null;
                if (array_key_exists("color", $MapData)){
                    $name = $MapData["color"];
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
}
