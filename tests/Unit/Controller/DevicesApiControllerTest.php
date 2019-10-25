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
use \OCA\Maps\Service\DevicesService;
use OCP\AppFramework\Http\TemplateResponse;


class DevicesApiControllerTest extends \PHPUnit\Framework\TestCase {
    private $appName;
    private $request;
    private $contacts;

    private $container;
    private $config;
    private $app;

    private $devicesApiController;
    private $devicesApiController2;
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
        $this->config = $c->query('ServerContainer')->getConfig();

        $this->devicesApiController = new DevicesApiController(
            $this->appName,
            $this->request,
            'test',
            $c->query('ServerContainer')->getUserFolder('test'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getShareManager(),
            $c->getServer()->getAppManager(),
            $c->getServer()->getUserManager(),
            $c->getServer()->getGroupManager(),
            $c->query('ServerContainer')->getL10N($c->query('AppName')),
            $c->query('ServerContainer')->getLogger(),
            new DevicesService(
                $c->query('ServerContainer')->getLogger(),
                $c->query('ServerContainer')->getL10N($c->query('AppName'))
            )
        );

        $this->devicesApiController2 = new DevicesApiController(
            $this->appName,
            $this->request,
            'test2',
            $c->query('ServerContainer')->getUserFolder('test2'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getShareManager(),
            $c->getServer()->getAppManager(),
            $c->getServer()->getUserManager(),
            $c->getServer()->getGroupManager(),
            $c->query('ServerContainer')->getL10N($c->query('AppName')),
            $c->query('ServerContainer')->getLogger(),
            new DevicesService(
                $c->query('ServerContainer')->getLogger(),
                $c->query('ServerContainer')->getL10N($c->query('AppName'))
            )
        );

        $this->utilsController = new UtilsController(
            $this->appName,
            $this->request,
            'test',
            $c->query('ServerContainer')->getUserFolder('test'),
            $c->query('ServerContainer')->getConfig(),
            $c->getServer()->getAppManager()
        );

        // delete
        $resp = $this->devicesApiController->getDevices('1.0');
        $data = $resp->getData();
        foreach ($data as $device) {
            $resp = $this->devicesApiController->deleteDevice($device['id']);
        }
    }

    public static function tearDownAfterClass(): void {
    }

    protected function tearDown(): void {
        // in case there was a failure and something was not deleted
    }

    public function testAddPoints() {
        $resp = $this->devicesApiController->getDevices('1.0');
        $data = $resp->getData();
        foreach ($data as $device) {
            $resp = $this->devicesApiController->deleteDevice($device['id']);
        }

        // delete device that does not exist
        $resp = $this->devicesApiController->deleteDevice(0);
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);
        $data = $resp->getData();
        $this->assertEquals('no such device', $data);

        // correct values
        $resp = $this->devicesApiController->addDevicePoint('1.0', 1.1, 2.2, 12345, 'testDevice', 1000, 99, 50);
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $deviceId = $data['deviceId'];
        $pointId = $data['pointId'];

        $_SERVER['HTTP_USER_AGENT'] = 'testBrowser';
        $ts = (new \DateTime())->getTimestamp();
        $resp = $this->devicesApiController->addDevicePoint('1.0', 1.2, 2.3, null, null, 1001, 100, 5);
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $deviceId2 = $data['deviceId'];
        $pointId2 = $data['pointId'];
        // test user agent is correct
        $resp = $this->devicesApiController->getDevices('1.0');
        $data = $resp->getData();
        $d2Found = false;
        foreach ($data as $device) {
            if ($device['id'] === $deviceId2) {
                $this->assertEquals('testBrowser', $device['user_agent']);
                $d2Found = true;
            }
        }
        $this->assertEquals(true, $d2Found);

        // This happens with a request such as /api/1.0/devices?lat=1.1&lng=2.2&timestamp=&user_agent=testDevice&altitude=&battery=&accuracy=
        $resp = $this->devicesApiController->addDevicePoint('1.0', 1.1, 2.2, '', 'testDevice', '', '', '');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $deviceId3 = $data['deviceId'];
        $pointId3 = $data['pointId'];

        // test point values
        $resp = $this->devicesApiController->getDevicePoints($deviceId2);
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals(true, count($data) === 1);
        $this->assertEquals(true, $data[0]['timestamp'] >= $ts);

        // invalid values
        $resp = $this->devicesApiController->addDevicePoint('1.0', 'aaa', 2.2, 12345, 'testDevice', 1000, 99, 50);
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);
        $data = $resp->getData();
        $this->assertEquals('invalid values', $data);

        $resp = $this->devicesApiController->addDevicePoint('1.0', 1.1, 'aaa', 12345, 'testDevice', 1000, 99, 50);
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);
        $data = $resp->getData();
        $this->assertEquals('invalid values', $data);
    }

    public function testEditDevice() {
        $resp = $this->devicesApiController->getDevices('1.0');
        $data = $resp->getData();
        foreach ($data as $device) {
            $resp = $this->devicesApiController->deleteDevice($device['id']);
        }

        $resp = $this->devicesApiController->addDevicePoint('1.0', 1.1, 2.2, 12345, 'testDevice', 1000, 99, 50);
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $deviceId = $data['deviceId'];
        $pointId = $data['pointId'];

        $resp = $this->devicesApiController->editDevice($deviceId, '#001122');
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals('#001122', $data['color']);

        $resp = $this->devicesApiController->editDevice(0, '#001122');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->devicesApiController->editDevice($deviceId, '');
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);
    }

    //public function testImportExportDevices() {
    //    $resp = $this->devicesApiController->getDevices('1.0');
    //    $data = $resp->getData();
    //    foreach ($data as $device) {
    //        $resp = $this->devicesApiController->deleteDevice($device['id']);
    //    }

    //    $userfolder = $this->container->query('ServerContainer')->getUserFolder('test');
    //    $content1 = file_get_contents('tests/test_files/devicesOk.gpx');
    //    $userfolder->newFile('devicesOk.gpx')->putContent($content1);

    //    $resp = $this->devicesApiController->importDevices('/devicesOk.gpx');
    //    $status = $resp->getStatus();
    //    $this->assertEquals(200, $status);
    //    $data = $resp->getData();
    //    $this->assertEquals(2, $data);

    //    $resp = $this->devicesApiController->importDevices('/doesNotExist.gpx');
    //    $status = $resp->getStatus();
    //    $this->assertEquals(400, $status);
    //    $data = $resp->getData();
    //    $this->assertEquals('File does not exist', $data);

    //    $resp = $this->devicesApiController->importDevices('/nc.jpg');
    //    $status = $resp->getStatus();
    //    $this->assertEquals(400, $status);

    //    $resp = $this->devicesApiController->importDevices('/Maps');
    //    $status = $resp->getStatus();
    //    $this->assertEquals(400, $status);

    //    // get ids
    //    $devices = [];
    //    $resp = $this->devicesApiController->getDevices('1.0');
    //    $data = $resp->getData();
    //    foreach ($data as $device) {
    //        $id = $device['id'];
    //        $devices[$id] = $device;
    //    }
    //    // get number of points
    //    foreach ($devices as $id=>$device) {
    //        $resp = $this->devicesApiController->getDevicePoints($id);
    //        $status = $resp->getStatus();
    //        $this->assertEquals(200, $status);
    //        $data = $resp->getData();
    //        $devices[$id]['nbPoints'] = count($data);
    //    }

    //    // export and compare
    //    $ids = array_keys($devices);
    //    $resp = $this->devicesApiController->exportDevices($ids, null, null, true);
    //    $status = $resp->getStatus();
    //    $this->assertEquals(200, $status);
    //    $exportPath = $resp->getData();
    //    $this->assertEquals(true, $userfolder->nodeExists($exportPath));

    //    // parse xml and compare number of devices and points
    //    $xmLData = $userfolder->get($exportPath)->getContent();
    //    $xml = simplexml_load_string($xmLData);
    //    $trks = $xml->trk;
    //    // number of devices
    //    $this->assertEquals(count($ids), count($trks));
    //    $pointCountExport = [];
    //    // count exported points per device
    //    foreach ($trks as $trk) {
    //        $name = (string)$trk->name[0];
    //        $pointCountExport[$name] = count($trk->trkseg[0]->trkpt);
    //    }
    //    // check that it matches the data in the DB
    //    foreach ($devices as $id => $device) {
    //        $this->assertEquals($device['nbPoints'], $pointCountExport[$device['user_agent']]);
    //    }

    //    // export error
    //    $resp = $this->devicesApiController->exportDevices(null, null, null, true);
    //    $status = $resp->getStatus();
    //    $this->assertEquals(400, $status);
    //    $data = $resp->getData();
    //    $this->assertEquals('No device to export', $data);

    //    $userfolder->get('/Maps')->delete();
    //    $userfolder->newFile('Maps')->putContent('dummy content');
    //    $resp = $this->devicesApiController->exportDevices($ids, null, null, true);
    //    $status = $resp->getStatus();
    //    $this->assertEquals(400, $status);
    //    $data = $resp->getData();
    //    $this->assertEquals('/Maps is not a directory', $data);
    //    $userfolder->get('/Maps')->delete();

    //    // delete all points
    //    $resp = $this->devicesApiController->getDevices('1.0');
    //    $data = $resp->getData();
    //    foreach ($data as $device) {
    //        $resp = $this->devicesApiController->deleteDevice($device['id']);
    //    }

    //    // and then try to export
    //    $resp = $this->devicesApiController->exportDevices($ids, null, null, true);
    //    $status = $resp->getStatus();
    //    $this->assertEquals(400, $status);
    //    $data = $resp->getData();
    //    $this->assertEquals('Nothing to export', $data);
    //}

    public function testEditDevices() {
        $this->assertEquals(true, 1==1);
        //// valid edition
        //$resp = $this->favoritesController->addFavorite('a', 3.1, 4.1, 'cat1', null, null);
        //$favId = $resp->getData()['id'];

        //$resp = $this->favoritesController->editFavorite($favId, 'aa', 3.2, 4.2, 'cat2', 'comment', 'ext');
        //$status = $resp->getStatus();
        //$this->assertEquals(200, $status);
        //$data = $resp->getData();
        //$this->assertEquals($favId, $data['id']);

        //$resp = $this->favoritesController->getFavorites();
        //$favs = $resp->getData();
        //$seen = false;
        //foreach ($favs as $fav) {
        //    if ($fav['id'] === $favId) {
        //        $seen = true;
        //        $this->assertEquals('aa', $fav['name']);
        //        $this->assertEquals(3.2, $fav['lat']);
        //        $this->assertEquals(4.2, $fav['lng']);
        //        $this->assertEquals('cat2', $fav['category']);
        //        $this->assertEquals('comment', $fav['comment']);
        //        $this->assertEquals('ext', $fav['extensions']);
        //    }
        //}
        //$this->assertEquals(true, $seen);

        //// invalid edition
        //$resp = $this->favoritesController->editFavorite($favId, 'aa', 'invalid lat', 4.2, 'cat2', 'comment', 'ext');
        //$status = $resp->getStatus();
        //$this->assertEquals(400, $status);
        //$data = $resp->getData();
        //$this->assertEquals('invalid values', $data);

        //$resp = $this->favoritesController->editFavorite(-1, 'aa', 'invalid lat', 4.2, 'cat2', 'comment', 'ext');
        //$this->assertEquals(400, $status);
        //$data = $resp->getData();
        //$this->assertEquals('no such favorite', $data);

        //// rename category
        //$resp = $this->favoritesController->addFavorite('b', 3.1, 4.2, 'cat1', null, null);
        //$resp = $this->favoritesController->addFavorite('one', 3.1, 4.2, 'cat2', null, null);

        //$resp = $this->favoritesController->renameCategories(['cat1'], 'cat1RENAMED');
        //$status = $resp->getStatus();
        //$this->assertEquals(200, $status);
        //$data = $resp->getData();
        //$this->assertEquals('RENAMED', $data);
        //// check if renaming worked
        //$resp = $this->favoritesController->getFavorites();
        //$favs = $resp->getData();
        //$seen = false;
        //foreach ($favs as $fav) {
        //    if ($fav['name'] === 'b') {
        //        $seen = true;
        //        $this->assertEquals('cat1RENAMED', $fav['category']);
        //    }
        //}
        //$this->assertEquals(true, $seen);
    }

}
