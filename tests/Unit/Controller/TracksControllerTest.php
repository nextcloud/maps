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
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Server;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class TracksControllerTest extends TestCase {
	private string $appName;
	private MockObject&IRequest $request;
	private ContainerInterface $container;
	private Application $app;
	private TracksController $tracksController;
	private TracksService $tracksService;
	private IRootFolder $rootFolder;

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();

		$user = $c->get(IUserManager::class)->get('test');
		$user2 = $c->get(IUserManager::class)->get('test2');
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
		$this->appName = 'maps';
		$this->request = $this->getMockBuilder(\OCP\IRequest::class)
			->disableOriginalConstructor()
			->getMock();

		$this->app = new Application();
		$this->container = $this->app->getContainer();
		$c = $this->container;

		$this->rootFolder = $c->get(IRootFolder::class);

		$this->tracksService = new TracksService(
			$c->get(\Psr\Log\LoggerInterface::class),
			$c->get(IFactory::class)->get('maps'),
			$this->rootFolder,
			$c->get(\OCP\Share\IManager::class),
			$c->get(\OCP\IDBConnection::class)
		);

		$this->tracksController = new TracksController(
			$this->appName,
			$this->request,
			$c->get(IFactory::class)->get('maps'),
			$c->get(TracksService::class),
			$this->rootFolder,
			'test',
		);

		$userfolder = $this->container->get(IRootFolder::class)->getUserFolder('test');

		// delete first
		if ($userfolder->nodeExists('testFile1.gpx')) {
			//echo "DELETE\n";
			$file = $userfolder->get('testFile1.gpx');
			$file->delete();
		}
		// delete db
		$qb = Server::get(\OCP\IDBConnection::class)->getQueryBuilder();
		$qb->delete('maps_tracks')
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
		$this->app->getContainer();

		$userfolder = $this->container->get(IRootFolder::class)->getUserFolder('test');
		// delete files
		if ($userfolder->nodeExists('testFile1.gpx')) {
			$file = $userfolder->get('testFile1.gpx');
			$file->delete();
		}
		// delete db
		$qb = Server::get(\OCP\IDBConnection::class)->getQueryBuilder();
		$qb->delete('maps_tracks')
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter('test', IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
	}

	public function testAddGetTracks(): void {
		$userfolder = $this->container->get(IRootFolder::class)->getUserFolder('test');

		$filename = 'tests/test_files/testFile1.gpx';
		$content1 = file_get_contents($filename);
		$file = $userfolder->newFile('testFile1.gpx');
		$file->putContent($content1);
		$file->touch();

		$resp = $this->tracksController->getTracks();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		$foundTestFile = false;
		foreach ($data as $v) {
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
		foreach ($data as $v) {
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
		$this->assertEquals(true, strlen((string)$meta) > 0);

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
		foreach ($data as $v) {
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
