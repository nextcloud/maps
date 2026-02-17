<?php

declare(strict_types=1);

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
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

final class PageControllerTest extends TestCase {
	private PageController $controller;

	private string $userId = 'john';

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$initialState = $this->createMock(IInitialState::class);
		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);

		$this->controller = new PageController(
			'maps',
			$request,
			$this->userId,
			$eventDispatcher,
			$appConfig,
			$initialState,
			$urlGenerator,
		);
	}

	public function testIndex(): void {
		$result = $this->controller->index();

		$this->assertEquals('main', $result->getTemplateName());
		$this->assertInstanceOf(TemplateResponse::class, $result);
	}

	public function testOpenGeoLink(): void {
		$result = $this->controller->openGeoLink('geo:1.1,2.2');

		$this->assertEquals('main', $result->getTemplateName());
		$this->assertInstanceOf(TemplateResponse::class, $result);
	}

}
