<?php

/**
 * @copyright Copyright (c) 2019, Paul Schwörer <hello@paulschwoerer.de>
 *
 * @author Paul Schwörer <hello@paulschwoerer.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Maps\Controller;

use OC;
use OC\AppFramework\Http;
use OCA\Maps\AppInfo\Application;
use OCA\Maps\DB\FavoriteShare;
use OCA\Maps\DB\FavoriteShareMapper;
use OCA\Maps\Service\FavoritesService;
use OCP\IServerContainer;
use PHPUnit\Framework\TestCase;

class PublicFavoritesApiControllerTest extends TestCase {
	/* @var PublicFavoritesApiController */
	private $publicFavoritesApiController;

	private $config;

	/* @var FavoritesService */
	private $favoritesService;

	/* @var FavoriteShareMapper */
	private $favoriteShareMapper;

	protected function setUp(): void {
		// Begin transaction
		$db = OC::$server->query(\OCP\IDBConnection::class);
		$db->beginTransaction();

		$container = (new Application())->getContainer();

		$appName = $container->query('AppName');

		$requestMock = $this->getMockBuilder('OCP\IRequest')->getMock();
		$sessionMock = $this->getMockBuilder('OCP\ISession')->getMock();

		$this->config = $container->query(IServerContainer::class)->getConfig();

		$this->favoritesService = new FavoritesService(
			$container->query(IServerContainer::class)->get(\Psr\Log\LoggerInterface::class),
			$container->query(IServerContainer::class)->getL10N($appName),
			$container->query(IServerContainer::class)->getSecureRandom(),
			$container->query(\OCP\IDBConnection::class)
		);

		$this->favoriteShareMapper = new FavoriteShareMapper(
			$container->query(\OCP\IDBConnection::class),
			$container->query(IServerContainer::class)->getSecureRandom(),
			$container->query(IserverContainer::class)->getRootFolder()
		);

		$this->publicFavoritesApiController = new PublicFavoritesApiController(
			$appName,
			$requestMock,
			$sessionMock,
			$this->favoritesService,
			$this->favoriteShareMapper
		);
	}

	protected function tearDown(): void {
		// Rollback transaction
		$db = OC::$server->query(\OCP\IDBConnection::class);
		$db->rollBack();
	}

	public function testGetFavorites() {
		$testUser = 'test099897';
		$categoryName = 'test89774590';

		$this->favoritesService
			->addFavoriteToDB($testUser, 'Test1', 0, 0, $categoryName, '', null);

		/* @var FavoriteShare */
		$share = $this->favoriteShareMapper->create($testUser, $categoryName);

		// Mock token sent by request
		$this->publicFavoritesApiController->setToken($share->getToken());

		$response = $this->publicFavoritesApiController->getFavorites();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());

		$data = $response->getData();

		$this->assertIsArray($data);
		$this->assertArrayHasKey('share', $data);
		$this->assertArrayHasKey('favorites', $data);

		$this->assertEquals($testUser, $data['share']->getOwner());
		$this->assertEquals($categoryName, $data['share']->getCategory());
		$this->assertEquals($share->getToken(), $data['share']->getToken());

		$this->assertEquals(1, count($data['favorites']));

		$el = $data['favorites'][0];
		$this->assertEquals('Test1', $el['name']);
		$this->assertEquals($categoryName, $el['category']);
	}
}
