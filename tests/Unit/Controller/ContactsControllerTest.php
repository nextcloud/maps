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

use OCA\DAV\CardDAV\CardDavBackend;

use OCA\DAV\CardDAV\ContactsManager;
use OCA\DAV\Connector\Sabre\Principal;
use OCA\Maps\AppInfo\Application;
use OCA\Maps\Service\AddressService;
use OCP\IServerContainer;
use Psr\Log\LoggerInterface;

class ContactsControllerTest extends \PHPUnit\Framework\TestCase {
	private $appName;
	private $request;
	private $contacts;
	private $mapFolder;

	private $container;
	private $config;
	private $app;

	private $contactsController;
	private $contactsController2;
	private $utilsController;
	private $cdBackend;
	private $root;

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
		$this->app = new Application();
		$c = $this->app->getContainer();

		$this->appName = 'maps';
		$this->request = $this->getMockBuilder('\OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();
		$this->contacts = $this->getMockBuilder('OCP\Contacts\IManager')
			->disableOriginalConstructor()
			->getMock();

		$urlGenerator = $c->getServer()->getURLGenerator();

		$this->contactsManager = $c->query(IServerContainer::class)->getContactsManager();
		$this->cm = $c->query(ContactsManager::class);
		$this->cm->setupContactsProvider($this->contactsManager, 'test', $urlGenerator);

		$this->app = new Application();
		$this->container = $this->app->getContainer();
		$c = $this->container;
		$this->config = $c->query(IServerContainer::class)->getConfig();

		$this->appData = $this->getMockBuilder('\OCP\Files\IAppData')
			->disableOriginalConstructor()
			->getMock();

		$this->addressService = new AddressService(
			$c->query(IServerContainer::class)->getMemCacheFactory(),
			$c->query(IServerContainer::class)->get(LoggerInterface::class),
			$c->query(IServerContainer::class)->getJobList(),
			$this->appData,
			$c->query(IServerContainer::class)->query(\OCP\IDBConnection::class)
		);

		//$this->userPrincipalBackend = new Principal(
		//    $c->getServer()->getUserManager(),
		//    $c->getServer()->getGroupManager(),
		//    $c->getServer()->getShareManager(),
		//    \OC::$server->getUserSession(),
		//    $c->query(IServerContainer::class)->getConfig(),
		//    \OC::$server->getAppManager()
		//);
		$this->userPrincipalBackend = $this->getMockBuilder('OCA\DAV\Connector\Sabre\Principal')
			->disableOriginalConstructor()
			->getMock();

		$this->cdBackend = $c->query(IServerContainer::class)->query(CardDavBackend::class);
		$this->root = $c->query(IServerContainer::class)->getRootFolder();
		$this->mapFolder = $this->createMapFolder();


		$this->contactsController = new ContactsController(
			$this->appName,
			$this->request,
			$c->query(IServerContainer::class)->query(\OCP\IDBConnection::class),
			$this->contactsManager,
			$this->addressService,
			'test',
			$this->cdBackend,
			$c->query(IServerContainer::class)->getAvatarManager(),
			$this->root,
			$urlGenerator);
		//$this->contactsController = $this->getMockBuilder('OCA\Maps\Controller\ContactsController')
		//    ->disableOriginalConstructor()
		//    ->getMock();

		$this->contactsController2 = new ContactsController(
			$this->appName,
			$this->request,
			$c->query(IServerContainer::class)->query(\OCP\IDBConnection::class),
			$this->contactsManager,
			$this->addressService,
			'test2',
			$this->cdBackend,
			$c->query(IServerContainer::class)->getAvatarManager(),
			$this->root,
			$urlGenerator
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

	private function createMapFolder() {
		$userFolder = $this->root->getUserFolder('test');
		if ($userFolder->nodeExists('Map')) {
			return $userFolder->get('Map');
		} else {
			return $userFolder->newFolder('Map');
		}
	}

	public static function tearDownAfterClass(): void {
	}

	protected function tearDown(): void {
		// in case there was a failure and something was not deleted
	}

	public function testAddContact() {
		$c = $this->container;
		//$this->contacts->createOrUpdate()
		//var_dump($this->contactsManager->isEnabled());
		// TODO understand why this only returns system address book
		//var_dump($this->contactsManager->getUserAddressBooks());

		$resp = $this->contactsController->getContacts();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		//var_dump($data);
	}

	public function testAddContactMyMap() {
		$c = $this->container;
		//$this->contacts->createOrUpdate()
		//var_dump($this->contactsManager->isEnabled());
		// TODO understand why this only returns system address book
		//var_dump($this->contactsManager->getUserAddressBooks());

		$resp = $this->contactsController->getContacts($this->mapFolder->getId());
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$data = $resp->getData();
		//var_dump($data);
	}

}
