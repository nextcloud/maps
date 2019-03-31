<?php

namespace OCA\Maps\Controller;

use OCP\AppFramework\Http\TemplateResponse;


class PageControllerTest extends \PHPUnit\Framework\TestCase {
    private $controller;
    private $userId = 'john';

    public function setUp() {
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
