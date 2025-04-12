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

use OCA\Maps\AppInfo\Application;
use OCA\Maps\Service\FavoritesService;
use OCP\IServerContainer;

class FavoritesApiControllerTest extends \PHPUnit\Framework\TestCase {
	private $appName;
	private $request;
	private $contacts;

	private $container;
	private $config;
	private $app;
	private $root;

	private $favoritesApiController;
	private $favoritesApiController2;
	private $utilsController;

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();

		$user = $c->getServer()->getUserManager()->get('test');
		$user2 = $c->getServer()->getUserManager()->get('test2');
		$user3 = $c->getServer()->getUserManager()->get('test3');
		$group = $c->getServer()->getGroupManager()->get('group1test');
		$group2 = $c->getServer()->getGroupManager()->get('group2test');

		// CREATE DUMMY USERS
		if ($user === null) {
			$u1 = $c->getServer()->getUserManager()->createUser('test', 'tatotitoTUTU');
			$u1->setEMailAddress('toto@toto.net');
		}
		if ($user2 === null) {
			$u2 = $c->getServer()->getUserManager()->createUser('test2', 'plopinoulala000');
		}
		if ($user2 === null) {
			$u3 = $c->getServer()->getUserManager()->createUser('test3', 'yeyeahPASSPASS');
		}
		if ($group === null) {
			$c->getServer()->getGroupManager()->createGroup('group1test');
			$u1 = $c->getServer()->getUserManager()->get('test');
			$c->getServer()->getGroupManager()->get('group1test')->addUser($u1);
		}
		if ($group2 === null) {
			$c->getServer()->getGroupManager()->createGroup('group2test');
			$u2 = $c->getServer()->getUserManager()->get('test2');
			$c->getServer()->getGroupManager()->get('group2test')->addUser($u2);
		}
	}

	protected function setUp(): void {
		$this->appName = 'maps';
		$this->request = $this->getMockBuilder('\OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();
		$this->contacts = $this->getMockBuilder('OCP\Contacts\IManager')
			->disableOriginalConstructor()
			->getMock();

		$this->app = new Application();
		$this->container = $this->app->getContainer();
		$c = $this->container;
		$this->config = $c->query(IServerContainer::class)->getConfig();
		$this->root = $c->query(IServerContainer::class)->getRootFolder();

		$this->favoritesApiController = new FavoritesApiController(
			$this->appName,
			$this->request,
			$c->query(IServerContainer::class),
			$c->query(IServerContainer::class)->getConfig(),
			$c->getServer()->getShareManager(),
			$c->getServer()->getAppManager(),
			$c->getServer()->getUserManager(),
			$c->getServer()->getGroupManager(),
			$c->query(IServerContainer::class)->getL10N($c->query('AppName')),
			$c->query(FavoritesService::class),
			'test'
		);

		$this->favoritesApiController2 = new FavoritesApiController(
			$this->appName,
			$this->request,
			$c->query(IServerContainer::class),
			$c->query(IServerContainer::class)->getConfig(),
			$c->getServer()->getShareManager(),
			$c->getServer()->getAppManager(),
			$c->getServer()->getUserManager(),
			$c->getServer()->getGroupManager(),
			$c->query(IServerContainer::class)->getL10N($c->query('AppName')),
			$c->query(FavoritesService::class),
			'test2'
		);

		$this->utilsController = new UtilsController(
			$this->appName,
			$this->request,
			$c->query(IServerContainer::class)->getConfig(),
			$c->getServer()->getAppManager(),
			$this->root,
			'test'
		);
	}

	public static function tearDownAfterClass(): void {
		//$app = new Application();
		//$c = $app->getContainer();
		//$user = $c->getServer()->getUserManager()->get('test');
		//$user->delete();
		//$user = $c->getServer()->getUserManager()->get('test2');
		//$user->delete();
		//$user = $c->getServer()->getUserManager()->get('test3');
		//$user->delete();
		//$c->getServer()->getGroupManager()->get('group1test')->delete();
		//$c->getServer()->getGroupManager()->get('group2test')->delete();
	}

	protected function tearDown(): void {
		// in case there was a failure and something was not deleted
		$resp = $this->favoritesApiController->getFavorites('1.0');
		$data = $resp->getData();
		foreach ($data as $fav) {
			$resp = $this->favoritesApiController->deleteFavorite($fav['id']);
		}
	}

	public function testAddFavorites() {
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
		$this->assertEquals(2, count($data));

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

	public function testEditFavorites() {
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
				$this->assertEquals(3.2, $fav['lat']);
				$this->assertEquals(4.2, $fav['lng']);
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
