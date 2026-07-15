<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 DeBaschdi
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Maps\Service;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use PHPUnit\Framework\TestCase;

class MyMapsServiceTest extends TestCase {
	public function testGetMyMapReturnsNullWhenNodeDoesNotExist(): void {
		$root = $this->createMock(IRootFolder::class);
		$userFolder = $this->createMock(Folder::class);

		$root->expects(self::once())
			->method('getUserFolder')
			->with('john')
			->willReturn($userFolder);
		$userFolder->expects(self::once())
			->method('getFirstNodeById')
			->with(42)
			->willReturn(null);

		$service = new MyMapsService($root);

		self::assertNull($service->getMyMap(42, 'john'));
	}
}
