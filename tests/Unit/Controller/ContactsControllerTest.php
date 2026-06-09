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
use OCA\Maps\AppInfo\Application;
use OCA\Maps\Service\AddressService;
use OCP\BackgroundJob\IJobList;
use OCP\Contacts\IManager as IContactsManager;
use OCP\Files\IAppData;
use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class ContactsControllerTest extends \PHPUnit\Framework\TestCase {
	private string $appName;
	private MockObject&IRequest $request;
	private $mapFolder;

	private ContainerInterface $container;
	private Application $app;
	private ContactsController $contactsController;
	private IContactsManager $contactsManager;
	private ContactsManager $cm;
	private MockObject&IAppData $appData;
	private AddressService $addressService;
	private $cdBackend;
	private $root;

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
		$this->app = new Application();
		$c = $this->app->getContainer();

		$this->appName = 'maps';
		$this->request = $this->createMock(IRequest::class);

		$urlGenerator = $c->get(IUrlGenerator::class);

		$this->contactsManager = $c->get(IContactsManager::class);
		$this->cm = $c->get(ContactsManager::class);
		$this->cm->setupContactsProvider($this->contactsManager, 'test', $urlGenerator);

		$this->app = new Application();
		$this->container = $this->app->getContainer();
		$c = $this->container;

		$this->appData = $this->createMock(IAppData::class);

		$this->addressService = new AddressService(
			$c->get(\OCP\ICacheFactory::class),
			$c->get(LoggerInterface::class),
			$c->get(IJobList::class),
			$this->appData,
			$c->get(\OCP\IDBConnection::class)
		);

		$this->cdBackend = $c->get(CardDavBackend::class);
		$this->root = $c->get(IRootFolder::class);
		$this->mapFolder = $this->createMapFolder();


		$this->contactsController = new ContactsController(
			$this->appName,
			$this->request,
			$c->get(\OCP\IDBConnection::class),
			$this->contactsManager,
			$this->addressService,
			'test',
			$this->cdBackend,
			$c->get(\OCP\IAvatarManager::class),
			$this->root,
			$urlGenerator);
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

	public function testAddContact(): void {
		//$this->contacts->createOrUpdate()
		//var_dump($this->contactsManager->isEnabled());
		// TODO understand why this only returns system address book
		//var_dump($this->contactsManager->getUserAddressBooks());

		$resp = $this->contactsController->getContacts();
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$resp->getData();
		//var_dump($data);
	}

	public function testAddContactMyMap(): void {
		//$this->contacts->createOrUpdate()
		//var_dump($this->contactsManager->isEnabled());
		// TODO understand why this only returns system address book
		//var_dump($this->contactsManager->getUserAddressBooks());

		$resp = $this->contactsController->getContacts($this->mapFolder->getId());
		$status = $resp->getStatus();
		$this->assertEquals(200, $status);
		$resp->getData();
		//var_dump($data);
	}

}
