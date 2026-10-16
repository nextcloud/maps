<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Maps\Controller;

use OCP\AppFramework\Services\IInitialState;
use OCP\Constants;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\TestCase;

class PublicUtilsControllerTest extends TestCase {
	public function testGetOptionsValuesIncludesTheRoutingSettings(): void {
		$file = $this->createMock(File::class);
		$file->method('getContent')->willReturn('{"tileLayer":"OpenStreetMap"}');
		$file->method('isUpdateable')->willReturn(false);
		$file->method('isDeletable')->willReturn(false);

		$folder = $this->createMock(Folder::class);
		$folder->method('get')->with('.index.maps')->willReturn($file);
		$folder->method('isReadable')->willReturn(true);
		$folder->method('isShareable')->willReturn(true);

		$share = $this->createMock(IShare::class);
		$share->method('getNode')->willReturn($folder);
		$share->method('getPermissions')->willReturn(Constants::PERMISSION_READ | Constants::PERMISSION_SHARE);
		$share->method('getShareOwner')->willReturn('alice');
		$share->method('getSharedBy')->willReturn('alice');

		$shareManager = $this->createMock(ShareManager::class);
		$shareManager->method('getShareByToken')->with('token')->willReturn($share);

		$user = $this->createMock(IUser::class);
		$user->method('isEnabled')->willReturn(true);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('alice')->willReturn($user);

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			fn (string $app, string $key): string => $key === 'osrmCarURL' ? 'https://routing.example.org/route/v1' : '',
		);

		$controller = new PublicUtilsController(
			'maps',
			$this->createMock(IRequest::class),
			$this->createMock(ISession::class),
			$this->createMock(IURLGenerator::class),
			$appConfig,
			$this->createMock(IInitialState::class),
			$userManager,
			$shareManager,
			$this->createMock(IRootFolder::class),
			$this->createMock(IEventDispatcher::class),
		);
		$controller->setToken('token');

		$values = $controller->getOptionsValues()->getData()['values'];

		self::assertSame('OpenStreetMap', $values['tileLayer']);
		self::assertSame('https://routing.example.org/route/v1', $values['osrmCarURL']);
		self::assertSame('', $values['mapboxAPIKEY']);
		self::assertTrue((bool)$values['isReadable']);
		self::assertFalse((bool)$values['isUpdateable']);
	}
}
