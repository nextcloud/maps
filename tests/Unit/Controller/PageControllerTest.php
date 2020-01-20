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
use OCP\AppFramework\Http\TemplateResponse;


class PageControllerTest extends \PHPUnit\Framework\TestCase {
    private $controller;
    private $userId = 'john';
    private $config;
    private $app;
    private $container;

    protected function setUp(): void {
        $request = $this->getMockBuilder('OCP\IRequest')->getMock();
        $initialStateService = $this->getMockBuilder('OCP\IInitialStateService')->getMock();
        $this->app = new Application();
        $this->container = $this->app->getContainer();
        $c = $this->container;
        $this->config = $c->query('ServerContainer')->getConfig();

        $this->oldGHValue = $this->config->getAppValue('maps', 'graphhopperURL');
        $this->config->setAppValue('maps', 'graphhopperURL', 'https://graphhopper.com:8080');

        $this->controller = new PageController(
            'maps', $request, $this->userId, $this->config, $initialStateService
        );
    }

    protected function tearDown(): void {
        $this->config->setAppValue('maps', 'graphhopperURL', $this->oldGHValue);
    }

    public function testIndex() {
        $result = $this->controller->index();

        $this->assertEquals('index', $result->getTemplateName());
        $this->assertTrue($result instanceof TemplateResponse);
    }

    public function testOpenGeoLink() {
        $result = $this->controller->openGeoLink('geo:1.1,2.2');

        $this->assertEquals('index', $result->getTemplateName());
        $this->assertTrue($result instanceof TemplateResponse);
    }

}
