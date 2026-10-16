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

use OCA\Files\Event\LoadSidebar;
use OCA\Viewer\Event\LoadViewer;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;

class PageControllerTest extends \PHPUnit\Framework\TestCase {
	private PageController $controller;
	private string $userId = 'john';
	private IAppConfig&MockObject $appConfig;
	private IInitialState&MockObject $initialState;
	private IEventDispatcher&MockObject $eventDispatcher;
	private IURLGenerator&MockObject $urlGenerator;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->controller = new PageController(
			'maps',
			$request,
			$this->userId,
			$this->eventDispatcher,
			$this->appConfig,
			$this->initialState,
			$this->urlGenerator,
		);
	}

	public function testIndex(): void {
		$result = $this->controller->index();

		$this->assertEquals('main', $result->getTemplateName());
		$this->assertTrue($result instanceof TemplateResponse);
	}

	public function testIndexLoadsTheSidebarAndTheViewerIfAvailable(): void {
		$expected = class_exists(LoadViewer::class) ? [LoadSidebar::class, LoadViewer::class] : [LoadSidebar::class];
		$dispatched = [];
		$this->eventDispatcher->method('dispatchTyped')
			->willReturnCallback(function (object $event) use (&$dispatched): void {
				$dispatched[] = $event::class;
			});

		$this->controller->index();

		$this->assertSame($expected, $dispatched);
	}

	public function testCanBeConstructedWithoutAuthenticatedUser(): void {
		$controller = new PageController(
			'maps',
			$this->createMock(IRequest::class),
			null,
			$this->eventDispatcher,
			$this->appConfig,
			$this->initialState,
			$this->urlGenerator,
		);

		$this->assertInstanceOf(PageController::class, $controller);
	}

	public function testOpenGeoLink(): void {
		$result = $this->controller->openGeoLink('geo:1.1,2.2');

		$this->assertEquals('main', $result->getTemplateName());
		$this->assertTrue($result instanceof TemplateResponse);
	}

}
