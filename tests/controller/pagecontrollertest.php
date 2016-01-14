<?php
/**
 * ownCloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sander Brand <brantje@gmail.com>
 * @copyright Sander Brand 2014
 */

namespace OCA\Maps\Controller;


use \OCP\IRequest;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\AppFramework\Http\JSONResponse;

use \OCA\Maps\AppInfo\Application;


class PageControllerTest extends \PHPUnit_Framework_TestCase {

	private $container;

	public function setUp () {
		$app = new Application();
		$this->container = $app->getContainer();
	}

	public function testIndex () {
		// swap out request
		$this->container['Request'] = $this->getMockBuilder('\OCP\IRequest')
			->getMock();
		$this->container['UserId'] = 'john';

		$result = $this->container['PageController']->index();

		$this->assertEquals(array('user' => 'john', 'devices' => []), $result->getParams());
		$this->assertEquals('main', $result->getTemplateName());
		$this->assertTrue($result instanceof TemplateResponse);
	}

}
