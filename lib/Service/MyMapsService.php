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

use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchQuery;
use OC\User\NoUserException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Search\ISearchComparison;
use Psr\Log\LoggerInterface;

class MyMapsService {

	public function __construct(
		private LoggerInterface $logger,
		private IRootFolder $root,
	) {
	}

	public function addMyMap($newName, $userId, $counter = 0) {
		$userFolder = $this->root->getUserFolder($userId);
		if (!$userFolder->nodeExists('/Maps')) {
			$userFolder->newFolder('Maps');
		}
		if ($userFolder->nodeExists('/Maps')) {
			$mapsFolder = $userFolder->get('/Maps');
			if (!($mapsFolder instanceof Folder)) {
				$response = '/Maps is not a directory';
				return $response;
			} elseif (!$mapsFolder->isCreatable()) {
				$response = '/Maps is not writeable';
				return $response;
			}
		} else {
			$response = 'Impossible to create /Maps';
			return $response;
		}
		if ($counter > 0) {
			$folderName = $newName.' '.$counter;
		} else {
			$folderName = $newName;
		}

		if ($mapsFolder->nodeExists($folderName)) {
			return $this->addMyMap($newName, $userId, $counter + 1);
		}
		$mapFolder = $mapsFolder->newFolder($folderName);
		$mapFolder->newFile('.index.maps', '{}');
		$isRoot = $mapFolder->getPath() === $userFolder->getPath();
		$MyMap = [
			'id' => $mapFolder->getId(),
			'name' => $folderName,
			'color' => null,
			'path' => $userFolder->getRelativePath($mapFolder->getPath()),
			'isShareable' => $mapFolder->isShareable(),
			'isDeletable' => $mapFolder->isDeletable(),
			'isCreatable' => $mapFolder->isCreatable(),
			'isUpdateable' => $mapFolder->isUpdateable(),
			'isReadable' => $mapFolder->isReadable(),
			'fileInfo' => [
				'id' => $mapFolder->getId(),
				'name' => '',
				'basename' => $isRoot ? '' : $mapFolder->getName(),
				'filename' => $userFolder->getRelativePath($mapFolder->getPath()),
				'etag' => $mapFolder->getEtag(),
				'permissions' => $mapFolder->getPermissions(),
				'type' => $mapFolder->getType(),
				'mime' => $mapFolder->getMimetype(),
				'lastmod' => $mapFolder->getMTime(),
				'path' => $userFolder->getRelativePath($mapFolder->getPath()),
				'sharePermissions' => $mapFolder->getPermissions(),
			]
		];
		return $MyMap;
	}

	private function node2MyMap($node, $userFolder):array {
		$mapData = json_decode($node->getContent(), true);
		if (isset($mapData['name'])) {
			$name = $mapData['name'];
		} else {
			$name = $node->getParent()->getName();
		}
		$color = null;
		if (isset($mapData['color'])) {
			$color = $mapData['color'];
		}
		$parentNode = $node->getParent();
		$isRoot = $parentNode->getPath() === $userFolder->getPath();
		$MyMap = [
			'id' => $parentNode->getId(),
			'name' => $name,
			'color' => $color,
			'path' => $userFolder->getRelativePath($parentNode->getPath()),
			'isShareable' => $parentNode->isShareable(),
			'isDeletable' => $parentNode->isDeletable(),
			'isCreatable' => $parentNode->isCreatable(),
			'isUpdateable' => $parentNode->isUpdateable(),
			'isReadable' => $parentNode->isReadable(),
			'fileInfo' => [
				'id' => $parentNode->getId(),
				'name' => '',
				'basename' => $isRoot ? '' : $parentNode->getName(),
				'filename' => $userFolder->getRelativePath($parentNode->getPath()),
				'etag' => $parentNode->getEtag(),
				'permissions' => $parentNode->getPermissions(),
				'type' => $parentNode->getType(),
				'mime' => $parentNode->getMimetype(),
				'lastmod' => $parentNode->getMTime(),
				'path' => $userFolder->getRelativePath($parentNode->getPath()),
				'sharePermissions' => $parentNode->getPermissions(),
			]
		];
		return $MyMap;
	}

	/**
	 * @param $userId
	 * @return array
	 * @throws NoUserException
	 * @throws NotPermittedException
	 */
	public function getAllMyMaps($userId) {
		$userFolder = $this->root->getUserFolder($userId);
		$MyMaps = [];
		$MyMapsNodes = $userFolder->search(new SearchQuery(
			new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'mimetype', 'application/x-nextcloud-maps'),
			0, 0, []));

		foreach ($MyMapsNodes as $node) {
			if ($node->getName() === '.index.maps') {
				$MyMaps[] = $this->node2MyMap($node, $userFolder);
			}
		}
		return $MyMaps;
	}

	public function updateMyMap($id, $values, $userId) {
		$userFolder = $this->root->getUserFolder($userId);
		$folders = $userFolder->getById($id);
		$folder = array_shift($folders);
		if (!($folder instanceof Folder)) {
			return [];
		}
		try {
			$file = $folder->get('.index.maps');
		} catch (NotFoundException $e) {
			$file = $folder->newFile('.index.maps', $content = '{}');
		}
		$mapData = json_decode($file->getContent(), true);
		$renamed = false;
		foreach ($values as $key => $value) {
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
		$file->putContent(json_encode($mapData, JSON_PRETTY_PRINT));
		if ($renamed) {
			if ($userFolder->nodeExists('/Maps')) {
				$mapsFolder = $userFolder->get('/Maps');
				if ($folder->getParent()->getId() === $mapsFolder->getId()) {
					try {
						$folder->move($mapsFolder->getPath().'/'.$newName);
					} catch (\Exception $e) {
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
		if (!($folder instanceof Folder)) {
			return 1;
		}
		if ($userFolder->nodeExists('/Maps')) {
			$mapsFolder = $userFolder->get('/Maps');
			if ($folder->getParent()->getId() === $mapsFolder->getId()) {
				try {
					$folder->delete();
				} catch (\Exception $e) {
					return 1;
				}
			} else {
				try {
					$file = $folder->get('.index.maps');
					$file->delete();
				} catch (\Exception $e) {
					return 1;
				}
			}
		}
		try {
			$file = $folder->get('.index.maps');
			$file->delete();
		} catch (NotFoundException $e) {
			return 1;
		}
		return 0;
	}

}
