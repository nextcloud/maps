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
use \OCA\Maps\AppInfo\Application;
use OCA\Maps\DB\FavoriteShareMapper;
use OCA\Maps\Service\FavoritesService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\IAppContainer;
use PHPUnit\Framework\TestCase;


class PublicPageControllerTest extends TestCase
{
  /* @var PublicPageController */
  private $publicPageController;

  private $config;

  /* @var Application */
  private $app;

  /* @var IAppContainer */
  private $container;

  /* @var FavoritesService */
  private $favoritesService;

  /* @var FavoriteShareMapper */
  private $favoriteShareMapper;

  protected function setUp(): void
  {
    // Begin transaction
    $db = OC::$server->getDatabaseConnection();
    $db->beginTransaction();

    $this->app = new Application();

    $this->container = $this->app->getContainer();
    $container = $this->container;

    $appName = $container->query('AppName');

    $this->favoritesService = new FavoritesService(
      $container->query('ServerContainer')->getLogger(),
      $container->query('ServerContainer')->getL10N($appName),
      $container->query('ServerContainer')->getSecureRandom()
    );

    $this->favoriteShareMapper = new FavoriteShareMapper(
      $container->query('DatabaseConnection'),
      $container->query('ServerContainer')->getSecureRandom()
    );

    $requestMock = $this->getMockBuilder('OCP\IRequest')->getMock();
    $sessionMock = $this->getMockBuilder('OCP\ISession')->getMock();

    $this->config = $container->query('ServerContainer')->getConfig();

    $this->publicPageController = new PublicPageController(
      $appName,
      $requestMock,
      $sessionMock,
      $this->config,
      $container->query('Logger'),
      $this->favoriteShareMapper
    );
  }

  protected function tearDown(): void
  {
    // Rollback transaction
    $db = OC::$server->getDatabaseConnection();
    $db->rollBack();
  }

  public function testSharedFavoritesCategory()
  {
    $categoryName = 'test908780';
    $testUserName = 'test';

    $this->favoritesService
      ->addFavoriteToDB($testUserName, "Test", 0, 0, $categoryName, "", null);
    $share = $this->favoriteShareMapper->create($testUserName, $categoryName);

    $result = $this->publicPageController->sharedFavoritesCategory($share->getToken());

    // Assertions
    $this->assertTrue($result instanceof TemplateResponse);
    $this->assertEquals('public/favorites_index', $result->getTemplateName());
  }

  public function testAccessRestrictionsForSharedFavoritesCategory()
  {
    $result = $this->publicPageController->sharedFavoritesCategory('test8348985');

    $this->assertTrue($result instanceof DataResponse);
    $this->assertEquals(Http::STATUS_NOT_FOUND, $result->getStatus());
  }
}
