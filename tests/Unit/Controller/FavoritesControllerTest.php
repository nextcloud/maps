<?php

declare(strict_types=1);

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @author Paul Schwörer <hello@paulschwoerer.de>
 * @copyright Julien Veyssier 2019
 * @copyright Paul Schwörer 2019
 */
namespace OCA\Maps\Controller;

use OCA\Maps\AppInfo\Application;
use OCA\Maps\DB\FavoriteShareMapper;
use OCA\Maps\Service\FavoritesService;
use OCP\AppFramework\Http;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IDateTimeZone;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class FavoritesControllerTest extends TestCase {
	public Folder $mapFolder;

	private ContainerInterface $container;

	private IRootFolder $root;

	private FavoritesController $favoritesController;

	private FavoritesController $favoritesController2;

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();

		$user = $c->get(IUserManager::class)->get('test');
		$user2 = $c->get(IUserManager::class)->get('test2');
		$user3 = $c->get(IUserManager::class)->get('test3');
		$group = $c->get(IGroupManager::class)->get('group1test');
		$group2 = $c->get(IGroupManager::class)->get('group2test');

		// CREATE DUMMY USERS
		if ($user === null) {
			$u1 = $c->get(IUserManager::class)->createUser('test', 'tatotitoTUTU');
			$u1->setEMailAddress('toto@toto.net');
		}

		if ($user2 === null) {
			$c->get(IUserManager::class)->createUser('test2', 'plopinoulala000');
		}

		if ($user3 === null) {
			$c->get(IUserManager::class)->createUser('test3', 'yeyeahPASSPASS');
		}

		if ($group === null) {
			$c->get(IGroupManager::class)->createGroup('group1test');
			$c->get(IGroupManager::class)->get('group1test')->addUser($user1);
		}

		if ($group2 === null) {
			$c->get(IGroupManager::class)->createGroup('group2test');
			$c->get(IGroupManager::class)->get('group2test')->addUser($user2);
		}
	}

	protected function setUp(): void {
		$appName = 'maps';
		$request = $this->createMock(IRequest::class);

		$app = new Application();
		$this->container = $app->getContainer();
		$c = $this->container;

		$this->root = $c->get(IRootFolder::class);

		$this->favoritesController = new FavoritesController(
			$appName,
			$request,
			$c->get(IAppConfig::class),
			$this->root,
			$c->get(IFactory::class)->get($appName),
			$c->get(FavoritesService::class),
			$c->get(IDateTimeZone::class),
			$c->get(FavoriteShareMapper::class),
			'test'
		);

		$this->favoritesController2 = new FavoritesController(
			$appName,
			$request,
			$c->get(IAppConfig::class),
			$this->root,
			$c->get(IFactory::class)->get($appName),
			$c->get(FavoritesService::class),
			$c->get(IDateTimeZone::class),
			$c->get(FavoriteShareMapper::class),
			'test2'
		);

		$this->mapFolder = $this->createMapFolder();
	}

	private function createMapFolder(): Folder {
		$userFolder = $this->root->getUserFolder('test');
		if ($userFolder->nodeExists('Map')) {
			$userFolder->get('Map')->delete();
		}

		return $userFolder->newFolder('Map');
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
		$resp = $this->favoritesController->getFavorites();
		$data = $resp->getData();
		foreach ($data as $fav) {
			$resp = $this->favoritesController->deleteFavorite($fav['id']);
		}
	}

	public function testAddFavorites(): void {
		// correct values
		$resp = $this->favoritesController->addFavorite('one', 3.1, 4.2, '', null, null);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('one', $data['name']);
		$id1 = $data['id'];

		$resp = $this->favoritesController->addFavorite('', 3.1, 4.2, '', null, null);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('', $data['name']);
		$id2 = $data['id'];

		// invalid values
		/*  ToDo: Probably test for type error
			$resp = $this->favoritesController->addFavorite('one', 'lat', 4.2, '', null, null);
			$status = $resp->getStatus();
			$this->assertEquals(400, $status);

			$resp = $this->favoritesController->addFavorite('one', 3.1, 'lon', '', null, null);
			$status = $resp->getStatus();
			$this->assertEquals(400, $status);*/

		// get favorites
		$resp = $this->favoritesController->getFavorites();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(2, $data);

		// delete created favorites
		$resp = $this->favoritesController->deleteFavorite($id1);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('DELETED', $data);

		$resp = $this->favoritesController->deleteFavorite($id2);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('DELETED', $data);

		// delete something that does not exist
		$resp = $this->favoritesController->deleteFavorite($id2);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
	}

	public function testAddFavoritesMyMap(): void {
		$this->mapFolder = $this->createMapFolder();
		$myMapId = $this->mapFolder->getId();
		// correct values
		$resp = $this->favoritesController->addFavorite('one', 3.1, 4.2, '', null, null, $myMapId);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('one', $data['name']);
		$id1 = $data['id'];

		$resp = $this->favoritesController->addFavorite('', 3.1, 4.2, '', null, null, $myMapId);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('', $data['name']);
		$id2 = $data['id'];

		$resp = $this->favoritesController->addFavorite('three', 3.1, 4.2, '', null, null, $myMapId);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('three', $data['name']);
		$id3 = $data['id'];

		// invalid values
		/*ToDo: Probably test for type error
		$resp = $this->favoritesController->addFavorite('one', 'lat', 4.2, '', null, null, $myMapId);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);

		$resp = $this->favoritesController->addFavorite('one', 3.1, 'lon', '', null, null, $myMapId);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);*/

		// get favorites
		$resp = $this->favoritesController->getFavorites($myMapId);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(3, $data);

		// delete created favorites0
		$resp = $this->favoritesController->deleteFavorite($id3, $myMapId);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('DELETED', $data);

		$resp = $this->favoritesController->deleteFavorites([$id1,$id2], $myMapId);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('DELETED', $data);

		// delete something that does not exist
		$resp = $this->favoritesController->deleteFavorite($id2, $myMapId);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
	}

	public function testImportExportFavorites(): void {
		$userfolder = $this->container->get(IRootFolder::class)->getUserFolder('test');
		$content1 = file_get_contents('tests/test_files/favoritesOk.gpx');
		$newFile = $userfolder->newFile('favoritesOk.gpx');
		$newFile->putContent($content1);

		$resp = $this->favoritesController->importFavorites('/favoritesOk.gpx');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals(27, $data['nbImported']);

		// get favorites
		$resp = $this->favoritesController->getFavorites();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(27, $data);
		$nbFavorites = count($data);
		$categoryCount = [];
		foreach ($data as $fav) {
			$categoryCount[$fav['category']] = isset($categoryCount[$fav['category']]) ? ($categoryCount[$fav['category']] + 1) : 1;
		}

		$categories = array_keys($categoryCount);

		// import errors
		$userfolder->newFile('dummy.pdf')->putContent('dummy content');

		$resp = $this->favoritesController->importFavorites('/dummy.gpx');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertEquals('File does not exist', $data);

		$resp = $this->favoritesController->importFavorites('/dummy.pdf');
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertEquals('Invalid file extension', $data);

		// export and compare
		$resp = $this->favoritesController->exportFavorites($categories, null, null, true);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$exportPath = $resp->getData();
		$this->assertEquals(true, $userfolder->nodeExists($exportPath));

		// parse xml and compare number of favorite for each category
		$xmLData = $userfolder->get($exportPath)->getContent();
		$xml = simplexml_load_string((string)$xmLData);
		$wpts = $xml->wpt;
		$this->assertCount($nbFavorites, $wpts);
		$categoryCountExport = [];
		foreach ($wpts as $wpt) {
			$cat = (string)$wpt->type[0];
			$categoryCountExport[$cat] = isset($categoryCountExport[$cat]) ? ($categoryCountExport[$cat] + 1) : 1;
		}

		foreach ($categoryCount as $cat => $nb) {
			$this->assertSame($categoryCountExport[$cat], $nb);
		}

		// export error
		$resp = $this->favoritesController->exportFavorites(null, null, null, true);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertEquals('Nothing to export', $data);

		$userfolder->get('/Maps')->delete();
		$userfolder->newFile('Maps')->putContent('dummy content');
		$resp = $this->favoritesController->exportFavorites($categories, null, null, true);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertEquals('/Maps is not a directory', $data);
		$userfolder->get('/Maps')->delete();

		// delete all favorites
		$resp = $this->favoritesController->getFavorites();
		$data = $resp->getData();
		$favIds = [];
		foreach ($data as $fav) {
			$favIds[] = $fav['id'];
		}

		$resp = $this->favoritesController->deleteFavorites($favIds);

		// and then try to export
		$resp = $this->favoritesController->exportFavorites($categories, null, null, true);

		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertEquals('Nothing to export', $data);
	}



	public function testEditFavorites(): void {
		// valid edition
		$resp = $this->favoritesController->addFavorite('a', 3.1, 4.1, 'cat1', null, null);
		$favId = $resp->getData()['id'];

		$resp = $this->favoritesController->editFavorite($favId, 'aa', 3.2, 4.2, 'cat2', 'comment', 'ext');
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals($favId, $data['id']);

		$resp = $this->favoritesController->getFavorites();
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
		//ToDo: Probably test for type errors
		//
		//    $resp = $this->favoritesController->editFavorite($favId, 'aa', 'invalid lat', 4.2, 'cat2', 'comment', 'ext');
		//    $status = $resp->getStatus();
		//    $this->assertEquals(400, $status);
		//    $data = $resp->getData();
		//    $this->assertEquals('Invalid values', $data);
		//
		//    $resp = $this->favoritesController->editFavorite(-1, 'aa', 'invalid lat', 4.2, 'cat2', 'comment', 'ext');
		//    $this->assertEquals(400, $status);
		//    $data = $resp->getData();
		//    $this->assertEquals('No such favorite', $data);

		// rename category
		$resp = $this->favoritesController->addFavorite('b', 3.1, 4.2, 'cat1', null, null);
		$resp = $this->favoritesController->addFavorite('one', 3.1, 4.2, 'cat2', null, null);

		$resp = $this->favoritesController->renameCategories(['cat1'], 'cat1RENAMED');

		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('RENAMED', $data);
		// check if renaming worked
		$resp = $this->favoritesController->getFavorites();
		$favs = $resp->getData();
		$seen = false;
		foreach ($favs as $fav) {
			if ($fav['name'] === 'b') {
				$seen = true;
				$this->assertEquals('cat1RENAMED', $fav['category']);
			}
		}

		$this->assertEquals(true, $seen);
	}

	public function testEditFavoritesMyMap(): void {
		$this->mapFolder = $this->createMapFolder();
		$myMapId = $this->mapFolder->getId();
		// valid edition
		$resp = $this->favoritesController->addFavorite('a', 3.1, 4.1, 'cat1', null, null, $myMapId);
		$favId = $resp->getData()['id'];

		$resp = $this->favoritesController->editFavorite($favId, 'aa', 3.2, 4.2, 'cat2', 'comment', 'ext', $myMapId);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals($favId, $data['id']);

		$resp = $this->favoritesController->getFavorites($myMapId);
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
			}
		}

		$this->assertEquals(true, $seen);

		// invalid edition
		//todo: check for type error
		//		$resp = $this->favoritesController->editFavorite($favId, 'aa', 'invalid lat', 4.2, 'cat2', 'comment', 'ext', $myMapId);
		//		$status = $resp->getStatus();
		//		$this->assertEquals(400, $status);
		//		$data = $resp->getData();
		//		$this->assertEquals('invalid values', $data);
		//
		//		$resp = $this->favoritesController->editFavorite(-1, 'aa', 'invalid lat', 4.2, 'cat2', 'comment', 'ext', $myMapId);
		//		$this->assertEquals(400, $status);
		//		$data = $resp->getData();
		//		$this->assertEquals('no such favorite', $data);

		// rename category
		$resp = $this->favoritesController->addFavorite('b', 3.1, 4.2, 'cat1', null, null, $myMapId);
		$resp = $this->favoritesController->addFavorite('one', 3.1, 4.2, 'cat2', null, null, $myMapId);

		$resp = $this->favoritesController->renameCategories(['cat1'], 'cat1RENAMED', $myMapId);

		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('RENAMED', $data);
		// check if renaming worked
		$resp = $this->favoritesController->getFavorites($myMapId);
		$favs = $resp->getData();
		$seen = false;
		foreach ($favs as $fav) {
			if ($fav['name'] === 'b') {
				$seen = true;
				$this->assertEquals('cat1RENAMED', $fav['category']);
			}
		}

		$this->assertEquals(true, $seen);
	}

	public function testShareUnShareCategory(): void {
		$categoryName = 'test3458565';

		$id = $this->favoritesController
			->addFavorite('Test', 0, 0, $categoryName, '', null)
			->getData()['id'];

		$response1 = $this->favoritesController->shareCategory($categoryName);
		$response2 = $this->favoritesController->unShareCategory($categoryName);

		$this->favoritesController->deleteFavorite($id);

		$this->assertEquals(Http::STATUS_OK, $response1->getStatus());
		$this->assertEquals(Http::STATUS_OK, $response2->getStatus());

		$this->assertIsString($response1->getData()->getToken());
		$this->assertTrue($response2->getData()['did_exist']);
	}

	public function testShareUnShareCategoryNotAuthorized(): void {
		$categoryName = 'test3458565';

		$id = $this->favoritesController2
			->addFavorite('Test2', 0, 0, $categoryName, '', null)
			->getData()['id'];

		$response1 = $this->favoritesController->shareCategory($categoryName);
		$response2 = $this->favoritesController->unShareCategory($categoryName);

		$this->favoritesController->deleteFavorite($id);

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response1->getStatus());
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response2->getStatus());
	}

	public function testShareUnShareNonExistentCategory(): void {
		$categoryName = 'non_existent';

		$response1 = $this->favoritesController->shareCategory($categoryName);
		$response2 = $this->favoritesController->unShareCategory($categoryName);

		$this->favoritesController->unShareCategory($categoryName);

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response1->getStatus());
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response2->getStatus());
	}

	public function testGetSharedCategories(): void {
		$categoryNames = ['test345456', 'test2345465', 'test65765'];
		$ids = [];

		foreach ($categoryNames as $categoryName) {
			$ids[] = $this->favoritesController
				->addFavorite('Test', 0, 0, $categoryName, '', null)
				->getData()['id'];
			$this->favoritesController->shareCategory($categoryName);
		}

		$categories = $this->favoritesController->getSharedCategories();

		$this->assertIsArray($categories->getData());

		$mappedCategories = array_map(fn ($el) => $el->getCategory(), $categories->getData());

		foreach ($categoryNames as $categoryName) {
			$this->assertContains($categoryName, $mappedCategories);
		}

		foreach ($categoryNames as $categoryName) {
			$this->favoritesController->unShareCategory($categoryName);
		}

		foreach ($ids as $id) {
			$this->favoritesController->deleteFavorite($id);
		}
	}

	public function testFavoriteShareIsRenamedCorrectly(): void {
		$categoryName = 'test03059035';
		$newCategoryName = 'test097876';

		$id = $this->favoritesController
			->addFavorite('Test', 0, 0, $categoryName, '', null)
			->getData()['id'];

		$this->favoritesController->shareCategory($categoryName);

		$this->favoritesController->renameCategories([$categoryName], $newCategoryName);

		$shares = $this->favoritesController->getSharedCategories()->getData();

		$shareNames = array_map(fn ($el) => $el->getCategory(), $shares);

		$this->favoritesController->deleteFavorite($id);
		$this->favoritesController->unShareCategory($newCategoryName);

		$this->assertContains($newCategoryName, $shareNames);
		$this->assertNotContains($categoryName, $shareNames);
	}
}
