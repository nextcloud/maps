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

namespace tests\Integration\Db;


use ChristophWurst\Nextcloud\Testing\DatabaseTransaction;
use ChristophWurst\Nextcloud\Testing\TestCase;
use OC;
use OCA\Maps\DB\FavoriteShare;
use OCA\Maps\DB\FavoriteShareMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class FavoriteShareMapperTest extends TestCase {
  use DatabaseTransaction;

  /* @var FavoriteShareMapper */
  private $mapper;

  public function setUp(): void {
    parent::setUp();

    $this->mapper = new FavoriteShareMapper(
      OC::$server->getDatabaseConnection(),
      OC::$server->getSecureRandom()
    );
  }

  public function testCreateByOwnerAndTokenIsSuccessful() {
    /* @var FavoriteShare */
    $share = $this->mapper->create("testUser", "testCategory");

    $this->assertIsString($share->getToken());
    $this->assertEquals("testUser", $share->getOwner());
    $this->assertEquals("testCategory", $share->getCategory());
  }

  public function testFindByTokenIsSuccessful() {
    /* @var FavoriteShare */
    $shareExpected = $this->mapper->create("testUser", "testCategory");

    /* @var FavoriteShare */
    $shareActual = $this->mapper->findByToken($shareExpected->getToken());

    $this->assertEquals($shareExpected->getToken(), $shareActual->getToken());
    $this->assertEquals($shareExpected->getOwner(), $shareActual->getOwner());
    $this->assertEquals($shareExpected->getCategory(), $shareActual->getCategory());
  }

  public function testFindByOwnerAndCategoryIsSuccessful() {
    /* @var FavoriteShare */
    $shareExpected = $this->mapper->create("testUser", "testCategory");

    /* @var FavoriteShare */
    $shareActual = $this->mapper->findByOwnerAndCategory("testUser", "testCategory");

    $this->assertEquals($shareExpected->getToken(), $shareActual->getToken());
    $this->assertEquals($shareExpected->getOwner(), $shareActual->getOwner());
    $this->assertEquals($shareExpected->getCategory(), $shareActual->getCategory());
  }

  public function testFindAllByOwnerIsSuccessfulAndDoesNotContainOtherShares() {
    /* @var FavoriteShare */
    $share1 = $this->mapper->create("testUser", "testCategory1");

    /* @var FavoriteShare */
    $share2 = $this->mapper->create("testUser", "testCategory2");

    $this->mapper->create("testUser2", "testCategory");

    /* @var array */
    $shares = $this->mapper->findAllByOwner("testUser");

    $shareTokens = array_map(function ($share) {
      return $share->getToken();
    }, $shares);

    $this->assertEquals(2, count($shareTokens));
    $this->assertContains($share1->getToken(), $shareTokens);
    $this->assertContains($share2->getToken(), $shareTokens);
  }

  public function testFindOrCreateByOwnerAndCategoryIsSuccessful() {
    /* @var FavoriteShare */
    $share = $this->mapper->findOrCreateByOwnerAndCategory("testUser", "testCategory");

    $this->assertIsString($share->getToken());
    $this->assertEquals("testUser", $share->getOwner());
    $this->assertEquals("testCategory", $share->getCategory());
  }

  public function testRemoveByOwnerAndCategoryIsSuccessful() {
    /* @var FavoriteShare */
    $share = $this->mapper->create("testUser", "testCategory");

    $this->mapper->removeByOwnerAndCategory($share->getOwner(), $share->getCategory());

    $this->expectException(DoesNotExistException::class);

    $this->mapper->findByOwnerAndCategory($share->getOwner(), $share->getCategory());
  }

}
