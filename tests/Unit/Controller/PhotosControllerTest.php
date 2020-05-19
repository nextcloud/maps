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

use \OCA\Maps\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCA\Maps\Service\GeophotoService;
use OCA\Maps\Service\PhotofilesService;
use OCA\Maps\Service\TracksService;
use OCA\Maps\Service\DevicesService;
use OCA\Maps\DB\GeophotoMapper;
use OCP\AppFramework\Http\TemplateResponse;
use \OCP\IServerContainer;


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

        $this->photoFileService = new PhotoFilesService(
            $c->query(IServerContainer::class)->getLogger(),
            $this->rootFolder,
            $c->query(IServerContainer::class)->getL10N($c->query('AppName')),
            $c->query(GeophotoMapper::class),
            $c->query(IServerContainer::class)->getShareManager(),
            $c->query(\OCP\BackgroundJob\IJobList::class)
        );

        $this->photosController = new PhotosController(
            $this->appName,
            $c->query(IServerContainer::class)->getLogger(),
            $this->request,
            $c->query(GeoPhotoService::class),
            $this->photoFileService,
            'test',
			$c->query('ServerContainer')->getUserFolder()
        );

        $this->photosController2 = new PhotosController(
            $this->appName,
            $c->query(IServerContainer::class)->getLogger(),
            $this->request,
            $c->query(GeoPhotoService::class),
            $this->photoFileService,
            'test2',
			$c->query('ServerContainer')->getUserFolder()
        );

        $this->utilsController = new UtilsController(
            $this->appName,
            $this->request,
            $c->query(IServerContainer::class)->getConfig(),
            $c->getServer()->getAppManager(),
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
        $qb = $qb->resetQueryParts();
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
        $file->move($userfolder->getPath().'/nc.jpg');
        $file = $userfolder->get('nc.jpg');
        $file->touch();

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
        $file->move($userfolder->getPath().'/nut.jpg');
        $file = $userfolder->get('nut.jpg');
        $file->touch();

        // following section is not valid anymore
        // TODO fix photo scan (or make it really better) and then adjust tests ;-)
        /*
        $this->photoFileService->addPhotoNow($file, 'test');

        $resp = $this->photosController->getPhotosFromDb();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(1, count($data));

        // non localized
        $resp = $this->photosController->getNonLocalizedPhotosFromDb();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(1, count($data));
        $this->assertEquals('/nut.jpg', $data[0]->path);

        foreach ($this->photoFileService->rescan('test') as $path) {
        }

        $resp = $this->photosController->getPhotosFromDb();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(1, count($data));

        // place photos
        $resp = $this->photosController->placePhotos(['/nut.jpg'], [1.2345], [9.8765]);
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(1, $data);

        $resp = $this->photosController->getPhotosFromDb();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(2, count($data));

        // reset coords
        $resp = $this->photosController->resetPhotosCoords(['/nut.jpg']);
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(1, $data);

        $resp = $this->photosController->getPhotosFromDb();
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(1, count($data));
        */
    }

}
