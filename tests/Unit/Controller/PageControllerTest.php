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

use OCP\AppFramework\Http\TemplateResponse;


class PageControllerTest extends \PHPUnit\Framework\TestCase {
    private $controller;
    private $userId = 'john';

    protected function setUp(): void {
        $request = $this->getMockBuilder('OCP\IRequest')->getMock();

        $this->controller = new PageController(
            'maps', $request, $this->userId
        );
    }

    public function testIndex() {
        $result = $this->controller->index();

        $this->assertEquals('index', $result->getTemplateName());
        $this->assertTrue($result instanceof TemplateResponse);
    }

}
