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

use OCA\Maps\AppInfo\Application;
use OCA\Maps\DB\FavoriteShareMapper;
use OCA\Maps\Service\FavoritesService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\L10N\IFactory;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class PublicFavoritePageControllerTest extends TestCase {
	private PublicFavoritePageController $publicPageController;
	private IConfig $config;
	private Application $app;
	private ContainerInterface $container;
	private FavoritesService $favoritesService;
	private FavoriteShareMapper $favoriteShareMapper;

	protected function setUp(): void {
		// Begin transaction
		$db = \OCP\Server::get(\OCP\IDBConnection::class);
		$db->beginTransaction();

		$this->app = new Application();

		$this->container = $this->app->getContainer();
		$container = $this->container;

		$appName = $container->get('appName');

		$this->favoritesService = new FavoritesService(
			$container->get(LoggerInterface::class),
			$container->get(IFactory::class)->get($appName),
			$container->get(ISecureRandom::class),
			$container->get(\OCP\IDBConnection::class)
		);

		$this->favoriteShareMapper = new FavoriteShareMapper(
			$container->get(\OCP\IDBConnection::class),
			$container->get(ISecureRandom::class),
			$container->get(IRootFolder::class)
		);

		$requestMock = $this->getMockBuilder('OCP\IRequest')->getMock();
		$sessionMock = $this->getMockBuilder('OCP\ISession')->getMock();

		$this->config = $container->get(IConfig::class);

		$this->publicPageController = new PublicFavoritePageController(
			$appName,
			$requestMock,
			$sessionMock,
			$this->config,
			$this->favoriteShareMapper
		);
	}

	protected function tearDown(): void {
		// Rollback transaction
		$db = \OCP\Server::get(\OCP\IDBConnection::class);
		$db->rollBack();
	}

	public function testSharedFavoritesCategory(): void {
		$categoryName = 'test908780';
		$testUserName = 'test';

		$this->favoritesService
			->addFavoriteToDB($testUserName, 'Test', 0, 0, $categoryName, '', null);
		$share = $this->favoriteShareMapper->create($testUserName, $categoryName);

		$result = $this->publicPageController->sharedFavoritesCategory($share->getToken());

		// Assertions
		$this->assertTrue($result instanceof TemplateResponse);
		$this->assertEquals('public/favorites_index', $result->getTemplateName());
	}

	public function testAccessRestrictionsForSharedFavoritesCategory(): void {
		$result = $this->publicPageController->sharedFavoritesCategory('test8348985');

		$this->assertTrue($result instanceof DataResponse);
		$this->assertEquals(Http::STATUS_NOT_FOUND, $result->getStatus());
	}
}
