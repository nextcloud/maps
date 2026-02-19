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
namespace OCA\Maps\Tests\Integration\Controller;

use OCP\App\IAppManager;
use OCP\AppFramework\App;
use PHPUnit\Framework\TestCase;

/**
 * This test shows how to make a small Integration Test. Query your class
 * directly from the container, only pass in mocks if needed and run your tests
 * against the database
 */
final class AppTest extends TestCase {

	private $container;

	protected function setUp(): void {
		$app = new App('maps');
		$this->container = $app->getContainer();
	}

	public function testAppInstalled(): void {
		$appManager = $this->container->query(IAppManager::class);
		$this->assertTrue($appManager->isInstalled('maps'));
	}
}
