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
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;

class PageControllerTest extends \PHPUnit\Framework\TestCase {
	private PageController $controller;
	private string $userId = 'john';
	private IConfig&MockObject $config;
	private IInitialState&MockObject $initialState;
	private IEventDispatcher&MockObject $eventDispatcher;
	private IURLGenerator&MockObject $urlGenerator;

	protected function setUp(): void {
		/** @var IRequest&MockObject */
		$request = $this->createMock(IRequest::class);
		$this->config = $this->createMock(IConfig::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->controller = new PageController(
			'maps',
			$request,
			$this->userId,
			$this->eventDispatcher,
			$this->config,
			$this->initialState,
			$this->urlGenerator,
		);
	}

	public function testIndex() {
		$result = $this->controller->index();

		$this->assertEquals('main', $result->getTemplateName());
		$this->assertTrue($result instanceof TemplateResponse);
	}

	public function testOpenGeoLink() {
		$result = $this->controller->openGeoLink('geo:1.1,2.2');

		$this->assertEquals('main', $result->getTemplateName());
		$this->assertTrue($result instanceof TemplateResponse);
	}

}
