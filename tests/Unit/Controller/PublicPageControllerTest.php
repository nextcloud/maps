<?php
/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
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
