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
use \OCA\Maps\Service\FavoritesService;
use OCP\AppFramework\Http\TemplateResponse;


class FavoritesControllerTest extends \PHPUnit\Framework\TestCase
{
  private $appName;
  private $request;
  private $contacts;

  private $container;
  private $config;
  private $app;

  private $pageController;
  private $pageController2;
  private $utilsController;

  public static function setUpBeforeClass(): void
  {
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

  protected function setUp(): void
  {
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

    $this->favoritesController = new FavoritesController(
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
      new FavoritesService(
        $c->query('ServerContainer')->getLogger(),
        $c->query('ServerContainer')->getL10N($c->query('AppName')),
        $c->query('ServerContainer')->getSecureRandom()
      ),
      $c->query('ServerContainer')->getDateTimeZone()
    );

    $this->favoritesController2 = new FavoritesController(
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
      new FavoritesService(
        $c->query('ServerContainer')->getLogger(),
        $c->query('ServerContainer')->getL10N($c->query('AppName')),
        $c->query('ServerContainer')->getSecureRandom()
      ),
      $c->query('ServerContainer')->getDateTimeZone()
    );

    $this->utilsController = new UtilsController(
      $this->appName,
      $this->request,
      'test',
      $c->query('ServerContainer')->getUserFolder('test'),
      $c->query('ServerContainer')->getConfig(),
      $c->getServer()->getAppManager()
    );
  }

  public static function tearDownAfterClass(): void
  {
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

  protected function tearDown(): void
  {
    // in case there was a failure and something was not deleted
    $resp = $this->favoritesController->getFavorites();
    $data = $resp->getData();
    foreach ($data as $fav) {
      $resp = $this->favoritesController->deleteFavorite($fav['id']);
    }
  }

  public function testAddFavorites()
  {
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
    $resp = $this->favoritesController->addFavorite('one', 'lat', 4.2, '', null, null);
    $status = $resp->getStatus();
    $this->assertEquals(400, $status);

    $resp = $this->favoritesController->addFavorite('one', 3.1, 'lon', '', null, null);
    $status = $resp->getStatus();
    $this->assertEquals(400, $status);

    // get favorites
    $resp = $this->favoritesController->getFavorites();
    $status = $resp->getStatus();
    $this->assertEquals(200, $status);
    $data = $resp->getData();
    $this->assertEquals(2, count($data));

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

  public function testImportExportFavorites()
  {
    $userfolder = $this->container->query('ServerContainer')->getUserFolder('test');
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
    $this->assertEquals(27, count($data));
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
    $xml = simplexml_load_string($xmLData);
    $wpts = $xml->wpt;
    $this->assertEquals($nbFavorites, count($wpts));
    $categoryCountExport = [];
    foreach ($wpts as $wpt) {
      $cat = (string)$wpt->type[0];
      $categoryCountExport[$cat] = isset($categoryCountExport[$cat]) ? ($categoryCountExport[$cat] + 1) : 1;
    }
    foreach ($categoryCount as $cat => $nb) {
      $this->assertEquals($categoryCountExport[$cat], $nb);
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
      array_push($favIds, $fav['id']);
    }
    $resp = $this->favoritesController->deleteFavorites($favIds);

    // and then try to export
    $resp = $this->favoritesController->exportFavorites($categories, null, null, true);
    $status = $resp->getStatus();
    $this->assertEquals(400, $status);
    $data = $resp->getData();
    $this->assertEquals('Nothing to export', $data);
  }

  public function testEditFavorites()
  {
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
        $this->assertEquals(3.2, $fav['lat']);
        $this->assertEquals(4.2, $fav['lng']);
        $this->assertEquals('cat2', $fav['category']);
        $this->assertEquals('comment', $fav['comment']);
        $this->assertEquals('ext', $fav['extensions']);
      }
    }
    $this->assertEquals(true, $seen);

    // invalid edition
    $resp = $this->favoritesController->editFavorite($favId, 'aa', 'invalid lat', 4.2, 'cat2', 'comment', 'ext');
    $status = $resp->getStatus();
    $this->assertEquals(400, $status);
    $data = $resp->getData();
    $this->assertEquals('invalid values', $data);

    $resp = $this->favoritesController->editFavorite(-1, 'aa', 'invalid lat', 4.2, 'cat2', 'comment', 'ext');
    $this->assertEquals(400, $status);
    $data = $resp->getData();
    $this->assertEquals('no such favorite', $data);

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

}
