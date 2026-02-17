<?php

declare(strict_types=1);

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

use OCA\Maps\AppInfo\Application;
use OCA\Maps\DB\FavoriteShareMapper;
use OCA\Maps\Service\FavoritesService;
use OCP\AppFramework\Http;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\ISession;
use OCP\L10N\IFactory;
use OCP\Security\ISecureRandom;
use OCP\Server;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PublicFavoritesApiControllerTest extends TestCase {
	private PublicFavoritesApiController $publicFavoritesApiController;

	private FavoritesService $favoritesService;

	private FavoriteShareMapper $favoriteShareMapper;

	protected function setUp(): void {
		// Begin transaction
		$db = Server::get(IDBConnection::class);
		$db->beginTransaction();

		$container = (new Application())->getContainer();

		$requestMock = $this->createMock(IRequest::class);
		$sessionMock = $this->createMock(ISession::class);

		$this->favoritesService = new FavoritesService(
			$container->get(LoggerInterface::class),
			$container->get(IFactory::class)->get('maps'),
			$container->get(IDBConnection::class)
		);

		$this->favoriteShareMapper = new FavoriteShareMapper(
			$container->get(IDBConnection::class),
			$container->get(ISecureRandom::class),
			$container->get(IRootFolder::class),
		);

		$this->publicFavoritesApiController = new PublicFavoritesApiController(
			'maps',
			$requestMock,
			$sessionMock,
			$this->favoritesService,
			$this->favoriteShareMapper
		);
	}

	protected function tearDown(): void {
		// Rollback transaction
		$db = Server::get(IDBConnection::class);
		$db->rollBack();
	}

	public function testGetFavorites(): void {
		$testUser = 'test099897';
		$categoryName = 'test89774590';

		$this->favoritesService
			->addFavoriteToDB($testUser, 'Test1', 0, 0, $categoryName, '', null);

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

		$this->assertCount(1, $data['favorites']);

		$el = $data['favorites'][0];
		$this->assertEquals('Test1', $el['name']);
		$this->assertEquals($categoryName, $el['category']);
	}
}
