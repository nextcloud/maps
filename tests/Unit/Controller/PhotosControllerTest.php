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
use OCA\Maps\DB\GeophotoMapper;
use OCA\Maps\Service\GeophotoService;
use OCA\Maps\Service\PhotofilesService;
use OCP\BackgroundJob\IJobList;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Server;
use OCP\Share\IManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PhotosControllerTest extends TestCase {

	private $container;


	private PhotosController $photosController;

	private PhotofilesService $photoFileService;

	private $GeoPhotosService;

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
		$this->container = $app->getContainer();
		$c = $this->container;

		$rootFolder = $c->get(IRootFolder::class);

		$this->GeoPhotosService = $c->get(GeoPhotoService::class);

		$this->photoFileService = new PhotoFilesService(
			$c->get(LoggerInterface::class),
			$c->get(ICacheFactory::class),
			$rootFolder,
			$c->get(IFactory::class)->get($c->get('AppName')),
			$c->get(GeophotoMapper::class),
			$c->get(IManager::class),
			$c->get(IJobList::class)
		);

		$this->photosController = new PhotosController(
			$appName,
			$request,
			$this->GeoPhotosService,
			$this->photoFileService,
			$rootFolder,
			'test'
		);

		$userfolder = $this->container->get(IRootFolder::class)->getUserFolder('test');
		// delete files
		if ($userfolder->nodeExists('nc.jpg')) {
			$file = $userfolder->get('nc.jpg');
			$file->delete();
		}

		if ($userfolder->nodeExists('nut.jpg')) {
			$file = $userfolder->get('nut.jpg');
			$file->delete();
		}

		// delete db
		$qb = Server::get(IDBConnection::class)->getQueryBuilder();
		$qb->delete('maps_photos')
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter('test', IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
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
	}

	public function testAddGetPhotos(): void {
		$userfolder = $this->container->get(IRootFolder::class)->getUserFolder('test');

		$filename = 'tests/test_files/nc.jpg';
		$handle = fopen($filename, 'rb');
		$content1 = fread($handle, filesize($filename));
		fclose($handle);
		$file = $userfolder->newFile('nc.jpgg');
		$fp = $file->fopen('wb');
		fwrite($fp, $content1);
		fclose($fp);
		$file->touch();
		// rename
		$file = $userfolder->get('nc.jpgg');
		$file->move($userfolder->getPath() . '/nc.jpg');
		$file = $userfolder->get('nc.jpg');
		$file->touch();

		$this->photoFileService->addPhotoNow($file, 'test');

		$filename = 'tests/test_files/nut.jpg';
		$handle = fopen($filename, 'rb');
		$content1 = fread($handle, filesize($filename));
		fclose($handle);
		$file = $userfolder->newFile('nut.jpgg');
		$fp = $file->fopen('wb');
		fwrite($fp, $content1);
		fclose($fp);
		$file->touch();
		// rename
		$file = $userfolder->get('nut.jpgg');
		$file->move($userfolder->getPath() . '/nut.jpg');
		$file = $userfolder->get('nut.jpg');
		$file->touch();

		// following section is not valid anymore
		// TODO fix photo scan (or make it really better) and then adjust tests ;-)
		$this->photoFileService->addPhotoNow($file, 'test');

		$this->GeoPhotosService->clearCache();
		$resp = $this->photosController->getPhotos();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(1, $data);
		$this->assertEquals('/nc.jpg', $data[0]->path);

		//Test .nomedia respected
		$this->GeoPhotosService->clearCache();
		$file = $userfolder->newFile('.maps.nomedia');
		$resp = $this->photosController->getPhotos();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(0, $data);
		$file->delete();

		//Test .noimage respected
		$this->GeoPhotosService->clearCache();
		$file = $userfolder->newFile('.maps.noimage');
		$resp = $this->photosController->getPhotos();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(0, $data);
		$file->delete();

		//Test .maps respected
		$this->GeoPhotosService->clearCache();
		$file = $userfolder->newFile('.index.maps');
		$resp = $this->photosController->getPhotos(null, true, true);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(0, $data);
		$file->delete();

		// non localized without track
		$this->GeoPhotosService->clearCache();
		$resp = $this->photosController->getNonLocalizedPhotos();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(0, $data);

		// with track
		$filename = 'tests/test_files/testFile1_locationNut.gpx';
		$content1 = file_get_contents($filename);
		$file = $userfolder->newFile('testFile1_locationNut.gpxx');
		$file->putContent($content1);
		//$file->touch();

		$file = $userfolder->get('testFile1_locationNut.gpxx');
		$file->move($userfolder->getPath() . '/testFile1_locationNut.gpx');
		//echo 'I MOVE TO '.$userfolder->getPath().'/testFile1.gpx'."\n";
		$file = $userfolder->get('testFile1_locationNut.gpx');
		$file->touch();

		$this->GeoPhotosService->clearCache();
		$resp = $this->photosController->getNonLocalizedPhotos();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(1, $data);
		$this->assertStringStartsWith('track:', array_key_first($data));
		$dataForTrack = array_shift($data);
		$this->assertCount(1, $dataForTrack);
		$this->assertEquals('/nut.jpg', $dataForTrack[0]->path);

		//Test .nomedia respected
		$this->GeoPhotosService->clearCache();
		$file = $userfolder->newFile('.maps.nomedia');
		$resp = $this->photosController->getNonLocalizedPhotos();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(0, $data);
		$file->delete();

		//Test .noimage respected
		$this->GeoPhotosService->clearCache();
		$file = $userfolder->newFile('.maps.noimage');
		$resp = $this->photosController->getNonLocalizedPhotos();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(0, $data);
		$file->delete();

		//Test .index.maps respected
		$this->GeoPhotosService->clearCache();
		$file = $userfolder->newFile('.index.maps');
		$resp = $this->photosController->getNonLocalizedPhotos(null, null, 250, 0, true, true);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(0, $data);
		$file->delete();

		//Test myMap
		$this->GeoPhotosService->clearCache();
		$file = $userfolder->newFile('.maps.noimage');
		$resp = $this->photosController->getNonLocalizedPhotos($userfolder->getId());
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(0, $data);
		$file->delete();

		// place photos
		$resp = $this->photosController->placePhotos(['/nut.jpg'], [1.2345], [9.8765]);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(1, $data);

		$this->GeoPhotosService->clearCache();
		$resp = $this->photosController->getPhotos();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(2, $data);

		$this->GeoPhotosService->clearCache();
		$resp = $this->photosController->getNonLocalizedPhotos();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(0, $data);

		// reset coords
		$resp = $this->photosController->resetPhotosCoords(['/nut.jpg']);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(1, $data);

		$this->GeoPhotosService->clearCache();
		$resp = $this->photosController->getPhotos();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(1, $data);

		//Test usage of cache adding photo
		$resp = $this->photosController->placePhotos(['/nut.jpg'], [1.2345], [9.8765]);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(1, $data);

		//And we expect that there is still zero non Localized Photo
		$resp = $this->photosController->getNonLocalizedPhotos();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertCount(0, $data);

	}

}
