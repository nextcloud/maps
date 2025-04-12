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
use OCA\Maps\DB\GeophotoMapper;
use OCA\Maps\Service\GeophotoService;
use OCA\Maps\Service\PhotofilesService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IServerContainer;

class PhotosControllerTest extends \PHPUnit\Framework\TestCase {
	private $appName;
	private $request;
	private $contacts;

	private $container;
	private $config;
	private $app;

	private $photosController;
	private $photosController2;
	private $utilsController;

	private $photoFileService;
	private $GeoPhotosService;

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

		$this->rootFolder = $c->query(IServerContainer::class)->getRootFolder();

		$this->GeoPhotosService = $c->query(GeoPhotoService::class);

		$this->photoFileService = new PhotoFilesService(
			$c->query(IServerContainer::class)->get(\Psr\Log\LoggerInterface::class),
			$c->query(IServerContainer::class)->getMemCacheFactory(),
			$this->rootFolder,
			$c->query(IServerContainer::class)->getL10N($c->query('AppName')),
			$c->query(GeophotoMapper::class),
			$c->query(IServerContainer::class)->getShareManager(),
			$c->query(\OCP\BackgroundJob\IJobList::class)
		);

		$this->photosController = new PhotosController(
			$this->appName,
			$this->request,
			$this->GeoPhotosService,
			$this->photoFileService,
			$this->rootFolder,
			'test'
		);

		$this->photosController2 = new PhotosController(
			$this->appName,
			$this->request,
			$c->query(GeoPhotoService::class),
			$this->photoFileService,
			$this->rootFolder,
			'test2'
		);

		$this->utilsController = new UtilsController(
			$this->appName,
			$this->request,
			$c->query(IServerContainer::class)->getConfig(),
			$c->getServer()->getAppManager(),
			$this->rootFolder,
			'test'
		);

		$userfolder = $this->container->query(IServerContainer::class)->getUserFolder('test');
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
		$qb = $c->query(IServerContainer::class)->query(\OCP\IDBConnection::class)->getQueryBuilder();
		$qb->delete('maps_photos')
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter('test', IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();
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
	}

	public function testAddGetPhotos() {
		$c = $this->app->getContainer();

		$userfolder = $this->container->query(IServerContainer::class)->getUserFolder('test');

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
		$resp = $this->photosController->getPhotos(null, null, true);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals(0, count($data));
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
		$this->assertEquals(0, count($data));
		$file->delete();

		//Test myMap
		$this->GeoPhotosService->clearCache();
		$file = $userfolder->newFile('.maps.noimage');
		$resp = $this->photosController->getNonLocalizedPhotos($userfolder->getId());
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals(0, count($data));
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

		//We do not clear the cache so we expect to still 1 photo
		$resp = $this->photosController->getPhotos();
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
