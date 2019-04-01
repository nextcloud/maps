<?php

namespace OCA\Maps\Controller;

use \OCA\Maps\AppInfo\Application;
use \OCA\Maps\Service\FavoritesService;
use OCP\AppFramework\Http\TemplateResponse;


class FavoritesControllerTest extends \PHPUnit\Framework\TestCase {
    private $appName;
    private $request;
    private $contacts;

    private $container;
    private $config;
    private $app;

    private $pageController;
    private $pageController2;
    private $utilsController;

    public static function setUpBeforeClass(): void {
        $app = new Application();
        $c = $app->getContainer();

        // clear test users
        $user = $c->getServer()->getUserManager()->get('test');
        if ($user !== null) {
            $user->delete();
        }
        $user = $c->getServer()->getUserManager()->get('test2');
        if ($user !== null) {
            $user->delete();
        }
        $user = $c->getServer()->getUserManager()->get('test3');
        if ($user !== null) {
            $user->delete();
        }

        // CREATE DUMMY USERS
        $u1 = $c->getServer()->getUserManager()->createUser('test', 'T0T0T0');
        $u1->setEMailAddress('toto@toto.net');
        $u2 = $c->getServer()->getUserManager()->createUser('test2', 'T0T0T0');
        $u3 = $c->getServer()->getUserManager()->createUser('test3', 'T0T0T0');
        $c->getServer()->getGroupManager()->createGroup('group1test');
        $c->getServer()->getGroupManager()->get('group1test')->addUser($u1);
        $c->getServer()->getGroupManager()->createGroup('group2test');
        $c->getServer()->getGroupManager()->get('group2test')->addUser($u2);
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
                $c->query('ServerContainer')->getL10N($c->query('AppName'))
            )
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
    }

    public static function tearDownAfterClass(): void {
        $app = new Application();
        $c = $app->getContainer();
        $user = $c->getServer()->getUserManager()->get('test');
        $user->delete();
        $user = $c->getServer()->getUserManager()->get('test2');
        $user->delete();
        $user = $c->getServer()->getUserManager()->get('test3');
        $user->delete();
        $c->getServer()->getGroupManager()->get('group1test')->delete();
        $c->getServer()->getGroupManager()->get('group2test')->delete();
    }

    protected function tearDown(): void {
        // in case there was a failure and something was not deleted
    }

    public function testAddFavorites() {
        // correct values
        $resp = $this->favoritesController->addFavorite('one', 3.1, 4.2, '', null, null);
        $status = $resp->getStatus();
        $this->assertEquals(200, $status);
        $data = $resp->getData();
        $this->assertEquals('one', $data['name']);

        // invalid values
        $resp = $this->favoritesController->addFavorite('', 3.1, 4.2, '', null, null);
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->favoritesController->addFavorite('one', 'lat', 4.2, '', null, null);
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);

        $resp = $this->favoritesController->addFavorite('one', 3.1, 'lon', '', null, null);
        $status = $resp->getStatus();
        $this->assertEquals(400, $status);
    }

}
