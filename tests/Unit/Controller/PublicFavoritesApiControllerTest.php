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
use OCA\Maps\AppInfo\Application;
use OCA\Maps\DB\FavoriteShare;
use OCA\Maps\DB\FavoriteShareMapper;
use OCA\Maps\Service\FavoritesService;
use PHPUnit\Framework\TestCase;


class PublicFavoritesApiControllerTest extends TestCase
{
  /* @var PublicFavoritesApiController */
  private $publicFavoritesApiController;

  private $config;

  /* @var FavoritesService */
  private $favoritesService;

  /* @var FavoriteShareMapper */
  private $favoriteShareMapper;

  protected function setUp(): void {
    // Begin transaction
    $db = OC::$server->getDatabaseConnection();
    $db->beginTransaction();

    $container = (new Application())->getContainer();

    $appName = $container->query('AppName');

    $requestMock = $this->getMockBuilder('OCP\IRequest')->getMock();
    $sessionMock = $this->getMockBuilder('OCP\ISession')->getMock();

    $this->config = $container->query('ServerContainer')->getConfig();

    $this->favoritesService = new FavoritesService(
      $container->query('ServerContainer')->getLogger(),
      $container->query('ServerContainer')->getL10N($appName),
      $container->query('ServerContainer')->getSecureRandom()
    );

    $this->favoriteShareMapper = new FavoriteShareMapper(
      $container->query('DatabaseConnection'),
      $container->query('ServerContainer')->getSecureRandom()
    );

    $this->publicFavoritesApiController = new PublicFavoritesApiController(
      $appName,
      $requestMock,
      $sessionMock,
      $this->favoritesService,
      $this->favoriteShareMapper
    );
  }

  protected function tearDown(): void
  {
    // Rollback transaction
    $db = OC::$server->getDatabaseConnection();
    $db->rollBack();
  }

  public function testGetFavorites() {
    $testUser = 'test099897';
    $categoryName = 'test89774590';

    $this->favoritesService
      ->addFavoriteToDB($testUser, "Test1", 0, 0, $categoryName, "", null);

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
