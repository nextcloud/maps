<?php

declare(strict_types=1);

/**
 * ownCloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sander Brand <brantje@gmail.com>, Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 * @copyright Sander Brand 2014, Vinzenz Rosenkranz 2016, 2017
 */
namespace OCA\Maps\AppInfo;

use OCA\DAV\Events\CardCreatedEvent;
use OCA\DAV\Events\CardDeletedEvent;
use OCA\DAV\Events\CardUpdatedEvent;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files\Event\LoadSidebar;
use OCA\Maps\Hooks\FileHooks;
use OCA\Maps\Listener\CardCreatedListener;
use OCA\Maps\Listener\CardDeletedListener;
use OCA\Maps\Listener\CardUpdatedListener;
use OCA\Maps\Listener\LoadAdditionalScriptsListener;
use OCA\Maps\Listener\LoadSidebarListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\EmptyFeaturePolicy;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Security\FeaturePolicy\AddFeaturePolicyEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'maps';

	public function __construct(array $urlParams = []) {
		parent::__construct('maps', $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// ... registration logic goes here ...

		// Register the composer autoloader for packages shipped by this app, if applicable
		include_once __DIR__ . '/../../vendor/autoload.php';
		$context->registerEventListener(
			LoadAdditionalScriptsEvent::class,
			LoadAdditionalScriptsListener::class
		);

		$context->registerEventListener(
			LoadSidebar::class,
			LoadSidebarListener::class
		);

		$context->registerEventListener(
			CardCreatedEvent::class,
			CardCreatedListener::class
		);
		$context->registerEventListener(
			CardUpdatedEvent::class,
			CardUpdatedListener::class
		);
		$context->registerEventListener(
			CardDeletedEvent::class,
			CardDeletedListener::class
		);
	}

	public function boot(IBootContext $context): void {
		$context->getAppContainer()->get(FileHooks::class)->register();

		$this->registerFeaturePolicy();
	}

	private function registerFeaturePolicy(): void {
		$dispatcher = $this->getContainer()->get(IEventDispatcher::class);

		$dispatcher->addListener(AddFeaturePolicyEvent::class, function (AddFeaturePolicyEvent $e): void {
			$fp = new EmptyFeaturePolicy();
			$fp->addAllowedGeoLocationDomain("'self'");

			$e->addPolicy($fp);
		});
	}

}
