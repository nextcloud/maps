<?php

declare(strict_types=1);

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
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Search\ISearchComparison;

class MyMapsService {

	public function __construct(
		private readonly IRootFolder $root,
	) {
	}

	public function addMyMap(string $newName, $userId, $counter = 0) {
		$userFolder = $this->root->getUserFolder($userId);
		if (!$userFolder->nodeExists('/Maps')) {
			$userFolder->newFolder('Maps');
		}

		if ($userFolder->nodeExists('/Maps')) {
			$mapsFolder = $userFolder->get('/Maps');
			if (!($mapsFolder instanceof Folder)) {
				return '/Maps is not a directory';
			}

			if (!$mapsFolder->isCreatable()) {
				return '/Maps is not writeable';
			}
		} else {
			return 'Impossible to create /Maps';
		}

		$folderName = $counter > 0 ? $newName . ' ' . $counter : $newName;

		if ($mapsFolder->nodeExists($folderName)) {
			return $this->addMyMap($newName, $userId, $counter + 1);
		}

		$mapFolder = $mapsFolder->newFolder($folderName);
		$mapFolder->newFile('.index.maps', '{}');

		$isRoot = $mapFolder->getPath() === $userFolder->getPath();
		return [
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
	}

	/**
	 * @return array<string, mixed>
	 */
	private function node2MyMap($node, $userFolder):array {
		$mapData = json_decode((string)$node->getContent(), true);
		$name = $mapData['name'] ?? $node->getParent()->getName();

		$color = null;
		if (isset($mapData['color'])) {
			$color = $mapData['color'];
		}

		$parentNode = $node->getParent();
		$isRoot = $parentNode->getPath() === $userFolder->getPath();
		return [
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
	}

	/**
	 * @param $userId
	 * @throws NoUserException
	 * @throws NotPermittedException
	 */
	public function getAllMyMaps($userId): array {
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

	/**
	 * Try to lookup a my map by id
	 *
	 * @param int $id The map id to lookup
	 * @param string $userId The current user id
	 * @return null|array Either the MyMap or null if not found with that id for the given user
	 */
	public function getMyMap(int $id, string $userId): ?array {
		$userFolder = $this->root->getUserFolder($userId);
		$node = $userFolder->getFirstNodeById($id);
		if ($node instanceof Folder) {
			try {
				$node = $node->get('.index.maps');
			} catch (NotFoundException) {
				return null;
			}
		}

		if ($node->getMimetype() === 'application/x-nextcloud-maps') {
			return $this->node2MyMap($node, $userFolder);
		}

		return null;
	}

	public function updateMyMap($id, $values, $userId) {
		$userFolder = $this->root->getUserFolder($userId);
		$folder = $userFolder->getFirstNodeById($id);
		if (!($folder instanceof Folder)) {
			return [];
		}

		try {
			/** @var File $file */
			$file = $folder->get('.index.maps');
		} catch (NotFoundException) {
			$file = $folder->newFile('.index.maps', '{}');
		}

		$mapData = json_decode((string)$file->getContent(), true);
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
		if ($renamed && $userFolder->nodeExists('/Maps')) {
			$mapsFolder = $userFolder->get('/Maps');
			if ($folder->getParent()->getId() === $mapsFolder->getId()) {
				try {
					$folder->move($mapsFolder->getPath() . '/' . $newName);
				} catch (\Exception) {
				}
			}
		}

		return $mapData;
	}

	public function deleteMyMap($id, $userId): int {
		$userFolder = $this->root->getUserFolder($userId);

		$folder = $userFolder->getFirstNodeById($id);
		if (!($folder instanceof Folder)) {
			return 1;
		}

		if ($userFolder->nodeExists('/Maps')) {
			$mapsFolder = $userFolder->get('/Maps');
			if ($folder->getParent()->getId() === $mapsFolder->getId()) {
				try {
					$folder->delete();
				} catch (\Exception) {
					return 1;
				}
			} else {
				try {
					$file = $folder->get('.index.maps');
					$file->delete();
				} catch (\Exception) {
					return 1;
				}
			}
		}

		try {
			$file = $folder->get('.index.maps');
			$file->delete();
		} catch (NotFoundException) {
			return 1;
		}

		return 0;
	}

}
