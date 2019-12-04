<?php


namespace tests\Unit\Mapper;


use ChristophWurst\Nextcloud\Testing\DatabaseTransaction;
use ChristophWurst\Nextcloud\Testing\TestCase;
use OC;
use OCA\Maps\DB\FavoriteShare;
use OCA\Maps\DB\FavoriteShareMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class FavoriteShareMapperTest extends TestCase {
  use DatabaseTransaction;

  /* @var FavoriteShareMapper */
  private $favoriteShareMapper;

  public function setUp(): void {
    parent::setUp();

    $this->favoriteShareMapper = new FavoriteShareMapper(
      OC::$server->getDatabaseConnection(),
      OC::$server->getSecureRandom()
    );
  }

  public function testCreateByOwnerAndTokenIsSuccessful() {
    /* @var FavoriteShare */
    $share = $this->favoriteShareMapper->create("testUser", "testCategory");

    $this->assertIsString($share->getToken());
    $this->assertEquals("testUser", $share->getOwner());
    $this->assertEquals("testCategory", $share->getCategory());
  }

  public function testFindByTokenIsSuccessful() {
    /* @var FavoriteShare */
    $shareExpected = $this->favoriteShareMapper->create("testUser", "testCategory");

    /* @var FavoriteShare */
    $shareActual = $this->favoriteShareMapper->findByToken($shareExpected->getToken());

    $this->assertEquals($shareExpected->getToken(), $shareActual->getToken());
    $this->assertEquals($shareExpected->getOwner(), $shareActual->getOwner());
    $this->assertEquals($shareExpected->getCategory(), $shareActual->getCategory());
  }

  public function testFindByOwnerAndCategoryIsSuccessful() {
    /* @var FavoriteShare */
    $shareExpected = $this->favoriteShareMapper->create("testUser", "testCategory");

    /* @var FavoriteShare */
    $shareActual = $this->favoriteShareMapper->findByOwnerAndCategory("testUser", "testCategory");

    $this->assertEquals($shareExpected->getToken(), $shareActual->getToken());
    $this->assertEquals($shareExpected->getOwner(), $shareActual->getOwner());
    $this->assertEquals($shareExpected->getCategory(), $shareActual->getCategory());
  }

  public function testFindAllByOwnerIsSuccessfulAndDoesNotContainOtherShares() {
    /* @var FavoriteShare */
    $share1 = $this->favoriteShareMapper->create("testUser", "testCategory1");

    /* @var FavoriteShare */
    $share2 = $this->favoriteShareMapper->create("testUser", "testCategory2");

    $this->favoriteShareMapper->create("testUser2", "testCategory");

    /* @var array */
    $shares = $this->favoriteShareMapper->findAllByOwner("testUser");

    $shareTokens = array_map(function ($share) {
      return $share->getToken();
    }, $shares);

    $this->assertEquals(2, count($shareTokens));
    $this->assertContains($share1->getToken(), $shareTokens);
    $this->assertContains($share2->getToken(), $shareTokens);
  }

  public function testFindOrCreateByOwnerAndCategoryIsSuccessful() {
    /* @var FavoriteShare */
    $share = $this->favoriteShareMapper->findOrCreateByOwnerAndCategory("testUser", "testCategory");

    $this->assertIsString($share->getToken());
    $this->assertEquals("testUser", $share->getOwner());
    $this->assertEquals("testCategory", $share->getCategory());
  }

  public function testRemoveByOwnerAndCategoryIsSuccessful() {
    /* @var FavoriteShare */
    $share = $this->favoriteShareMapper->create("testUser", "testCategory");

    $this->favoriteShareMapper->removeByOwnerAndCategory($share->getOwner(), $share->getCategory());

    $this->expectException(DoesNotExistException::class);

    $this->favoriteShareMapper->findByOwnerAndCategory($share->getOwner(), $share->getCategory());
  }

}
