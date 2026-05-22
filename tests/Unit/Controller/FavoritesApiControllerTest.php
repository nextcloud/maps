<?php

declare(strict_types=1);

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

use OCA\Maps\AppInfo\Application;
use OCA\Maps\Service\FavoritesService;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

final class FavoritesApiControllerTest extends TestCase {

	private FavoritesApiController $favoritesApiController;

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();

		$user = $c->get(IUserManager::class)->get('test');
		$user2 = $c->get(IUserManager::class)->get('test2');
		$c->get(IUserManager::class)->get('test3');
		$group = $c->get(IGroupManager::class)->get('group1test');
		$group2 = $c->get(IGroupManager::class)->get('group2test');

		// CREATE DUMMY USERS
		if ($user === null) {
			$u1 = $c->get(IUserManager::class)->createUser('test', 'tatotitoTUTU');
			$u1->setEMailAddress('toto@toto.net');
		}

		if ($user2 === null) {
			$u2 = $c->get(IUserManager::class)->createUser('test2', 'plopinoulala000');
			$u3 = $c->get(IUserManager::class)->createUser('test3', 'yeyeahPASSPASS');
		}

		if ($group === null) {
			$c->get(IGroupManager::class)->createGroup('group1test');
			$u1 = $c->get(IUserManager::class)->get('test');
			$c->get(IGroupManager::class)->get('group1test')->addUser($u1);
		}

		if ($group2 === null) {
			$c->get(IGroupManager::class)->createGroup('group2test');
			$u2 = $c->get(IUserManager::class)->get('test2');
			$c->get(IGroupManager::class)->get('group2test')->addUser($u2);
		}
	}

	protected function setUp(): void {
		$appName = 'maps';
		$request = $this->createMock('\OCP\IRequest');

		$app = new Application();
		$container = $app->getContainer();
		$c = $container;

		$this->favoritesApiController = new FavoritesApiController(
			$appName,
			$request,
			$c->get(IFactory::class)->get('maps'),
			$c->get(FavoritesService::class),
			'test'
		);
	}

	public static function tearDownAfterClass(): void {
		//$app = new Application();
		//$c = $app->getContainer();
		//$user = $c->get(IUserManager::class)->get('test');
		//$user->delete();
		//$user = $c->get(IUserManager::class)->get('test2');
		//$user->delete();
		//$user = $c->get(IUserManager::class)->get('test3');
		//$user->delete();
		//$c->get(IGroupManager::class)->get('group1test')->delete();
		//$c->get(IGroupManager::class)->get('group2test')->delete();
	}

	protected function tearDown(): void {
		// in case there was a failure and something was not deleted
		$resp = $this->favoritesApiController->getFavorites('1.0');
		$data = $resp->getData();
		foreach ($data as $fav) {
			$resp = $this->favoritesApiController->deleteFavorite($fav['id']);
		}
	}

	public function testAddFavorites(): void {
		// correct values
		$resp = $this->favoritesApiController->addFavorite('1.0', 'one', 3.1, 4.2, '', null, null);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('one', $data['name']);
		$id1 = $data['id'];

		$resp = $this->favoritesApiController->addFavorite('1.0', '', 3.1, 4.2, '', null, null);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('', $data['name']);
		$id2 = $data['id'];

		// Invalid values
		$resp = $this->favoritesApiController->addFavorite('1.0', 'one', 'lat', 4.2, '', null, null);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		$resp = $this->favoritesApiController->addFavorite('1.0', 'one', 3.1, 'lon', '', null, null);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		// get favorites
		$resp = $this->favoritesApiController->getFavorites('1.0');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(2, $data);

		//// get favorites using etag
		//$etag = $resp->getEtag();
		//var_dump($etag);
		//$this->request->setHeader('If-None-Match', '"'.$etag.'"');
		//$resp = $this->favoritesApiController->getFavorites('1.0');
		//$status = $resp->getStatus();
		//$this->assertEquals(200, $status);
		//$data = $resp->getData();
		//$this->assertEquals(2, count($data));

		// delete created favorites
		$resp = $this->favoritesApiController->deleteFavorite($id1);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('DELETED', $data);

		$resp = $this->favoritesApiController->deleteFavorite($id2);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('DELETED', $data);

		// delete something that does not exist
		$resp = $this->favoritesApiController->deleteFavorite($id2);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
	}

	public function testEditFavorites(): void {
		// valid edition
		$resp = $this->favoritesApiController->addFavorite('1.0', 'a', 3.1, 4.1, 'cat1', null, null);
		$favId = $resp->getData()['id'];

		$resp = $this->favoritesApiController->editFavorite($favId, 'aa', 3.2, 4.2, 'cat2', 'comment', 'ext');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals($favId, $data['id']);

		$resp = $this->favoritesApiController->getFavorites('1.0');
		$favs = $resp->getData();
		$seen = false;
		foreach ($favs as $fav) {
			if ($fav['id'] === $favId) {
				$seen = true;
				$this->assertEquals('aa', $fav['name']);
				$this->assertEqualsWithDelta(3.2, $fav['lat'], PHP_FLOAT_EPSILON);
				$this->assertEqualsWithDelta(4.2, $fav['lng'], PHP_FLOAT_EPSILON);
				$this->assertEquals('cat2', $fav['category']);
				$this->assertEquals('comment', $fav['comment']);
				$this->assertEquals('ext', $fav['extensions']);
			}
		}

		$this->assertEquals(true, $seen);

		// invalid edition
		$resp = $this->favoritesApiController->editFavorite($favId, 'aa', 'invalid lat', 4.2, 'cat2', 'comment', 'ext');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertEquals('Invalid values', $data);

		$resp = $this->favoritesApiController->editFavorite(-1, 'aa', 'invalid lat', 4.2, 'cat2', 'comment', 'ext');
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertEquals('No such favorite', $data);
	}

}
