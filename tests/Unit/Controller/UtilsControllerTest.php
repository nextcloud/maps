<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Maps\Controller;

use OCP\App\IAppManager;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class UtilsControllerTest extends TestCase {
	public function testSetRoutingSettingsTrimsValuesAndIgnoresUnknownKeys(): void {
		$config = $this->createMock(IConfig::class);
		$config->expects(self::exactly(2))
			->method('setAppValue')
			->willReturnCallback(function (string $app, string $key, string $value): void {
				self::assertSame('maps', $app);
				self::assertSame([
					'osrmCarURL' => 'https://routing.example.org/route/v1',
					'osrmDEMO' => '1',
				][$key], $value);
			});

		$controller = new UtilsController(
			'maps',
			$this->createMock(IRequest::class),
			$config,
			$this->createMock(IAppManager::class),
			$this->createMock(IRootFolder::class),
			'john',
		);

		$controller->setRoutingSettings([
			'osrmCarURL' => " https://routing.example.org/route/v1\n",
			'osrmDEMO' => '1',
			'notARoutingSetting' => 'ignored',
		]);
	}
}
