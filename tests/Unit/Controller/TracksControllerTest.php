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
use OCA\Maps\Service\TracksService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IServerContainer;

class TracksControllerTest extends \PHPUnit\Framework\TestCase {
	private $appName;
	private $request;
	private $contacts;

	private $container;
	private $config;
	private $app;

	private $tracksController;
	private $tracksController2;
	private $utilsController;

	private $tracksService;

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

		$this->tracksService = new TracksService(
			$c->query(IServerContainer::class)->get(\Psr\Log\LoggerInterface::class),
			$c->query(IServerContainer::class)->getL10N($c->query('AppName')),
			$this->rootFolder,
			$c->query(IServerContainer::class)->getShareManager(),
			$c->query(IServerContainer::class)->query(\OCP\IDBConnection::class)
		);

		$this->tracksController = new TracksController(
			$this->appName,
			$this->request,
			$c->query(IServerContainer::class),
			$c->query(IServerContainer::class)->getConfig(),
			$c->query(IServerContainer::class)->getShareManager(),
			$c->getServer()->getAppManager(),
			$c->getServer()->getUserManager(),
			$c->getServer()->getGroupManager(),
			$c->query(IServerContainer::class)->getL10N($c->query('AppName')),
			$c->query(TracksService::class),
			'test',
		);

		$this->tracksController2 = new TracksController(
			$this->appName,
			$this->request,
			$c->query(IServerContainer::class),
			$c->query(IServerContainer::class)->getConfig(),
			$c->query(IServerContainer::class)->getShareManager(),
			$c->getServer()->getAppManager(),
			$c->getServer()->getUserManager(),
			$c->getServer()->getGroupManager(),
			$c->query(IServerContainer::class)->getL10N($c->query('AppName')),
			$c->query(TracksService::class),
			'test2',
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

		// delete first
		if ($userfolder->nodeExists('testFile1.gpx')) {
			//echo "DELETE\n";
			$file = $userfolder->get('testFile1.gpx');
			$file->delete();
		}
		// delete db
		$qb = $c->query(IServerContainer::class)->query(\OCP\IDBConnection::class)->getQueryBuilder();
		$qb->delete('maps_tracks')
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
		$c = $this->app->getContainer();

		$userfolder = $this->container->query(IServerContainer::class)->getUserFolder('test');
		// delete files
		if ($userfolder->nodeExists('testFile1.gpx')) {
			$file = $userfolder->get('testFile1.gpx');
			$file->delete();
		}
		// delete db
		$qb = $c->query(IServerContainer::class)->query(\OCP\IDBConnection::class)->getQueryBuilder();
		$qb->delete('maps_tracks')
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter('test', IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();
	}

	public function testAddGetTracks() {
		$c = $this->app->getContainer();

		$userfolder = $this->container->query(IServerContainer::class)->getUserFolder('test');

		$filename = 'tests/test_files/testFile1.gpx';
		$content1 = file_get_contents($filename);
		$file = $userfolder->newFile('testFile1.gpxx');
		$file->putContent($content1);
		//$file->touch();

		$file = $userfolder->get('testFile1.gpxx');
		$file->move($userfolder->getPath() . '/testFile1.gpx');
		//echo 'I MOVE TO '.$userfolder->getPath().'/testFile1.gpx'."\n";
		$file = $userfolder->get('testFile1.gpx');
		$file->touch();

		$resp = $this->tracksController->getTracks();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$foundTestFile = false;
		foreach ($data as $k => $v) {
			if ($v['file_path'] === '/testFile1.gpx') {
				$foundTestFile = true;
				break;
			}
		}
		$this->assertEquals(true, count($data) > 0);
		$this->assertEquals(true, $foundTestFile);

		foreach ($this->tracksService->rescan('test') as $path) {
			//echo $path."\n";
		}

		$resp = $this->tracksController->getTracks();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$foundTestFile = false;
		//var_dump($data);
		$trackId = null;
		foreach ($data as $k => $v) {
			if ($v['file_path'] === '/testFile1.gpx') {
				$foundTestFile = true;
				$trackId = $v['id'];
				$this->assertEquals(true, $v['color'] === null);
				break;
			}
		}
		$this->assertEquals(true, count($data) > 0);
		$this->assertEquals(true, $foundTestFile);

		// track content
		$resp = $this->tracksController->getTrackFileContent($trackId);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals(true, $content1 === $data['content']);
		$meta = $data['metadata'];
		$this->assertEquals(true, strlen($meta) > 0);

		// to get stored metadata
		$resp = $this->tracksController->getTrackFileContent($trackId);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals(true, $content1 === $data['content']);
		$this->assertEquals(true, $meta === $data['metadata']);

		// file that does not exist
		$resp = $this->tracksController->getTrackFileContent(0);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertEquals('File not found', $data);

		// edit track
		$resp = $this->tracksController->editTrack($trackId, '#002244', null, null);
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$this->assertEquals('EDITED', $data);

		// check new color
		$resp = $this->tracksController->getTracks();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$foundTestFile = false;
		foreach ($data as $k => $v) {
			if ($v['file_path'] === '/testFile1.gpx') {
				$foundTestFile = true;
				$this->assertEquals(true, $v['color'] === '#002244');
				break;
			}
		}
		$this->assertEquals(true, count($data) > 0);
		$this->assertEquals(true, $foundTestFile);

		// edit track that does not exist
		$resp = $this->tracksController->editTrack(0, '#002244', null, null);
		$status = $resp->getStatus();
		$this->assertEquals(400, $status);
		$data = $resp->getData();
		$this->assertEquals('No such track', $data);
	}

}
