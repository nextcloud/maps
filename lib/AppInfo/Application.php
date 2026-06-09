<?php

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
use OCA\Files_Trashbin\Events\NodeRestoredEvent;
use OCA\Maps\Listener\AddFeaturePolicyListener;
use OCA\Maps\Listener\CardCreatedListener;
use OCA\Maps\Listener\CardDeletedListener;
use OCA\Maps\Listener\CardUpdatedListener;
use OCA\Maps\Listener\LoadAdditionalScriptsListener;
use OCA\Maps\Listener\LoadSidebarListener;
use OCA\Maps\Listener\NodeListener;
use OCA\Maps\Listener\ShareListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeTouchedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Security\FeaturePolicy\AddFeaturePolicyEvent;
use OCP\Share\Events\BeforeShareDeletedEvent;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\Events\ShareDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'maps';

	public function __construct(array $urlParams = []) {
		parent::__construct('maps', $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// Register the composer autoloader for packages shipped by this app, if applicable
		include_once __DIR__ . '/../../vendor/autoload.php';

		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);
		$context->registerEventListener(LoadSidebar::class, LoadSidebarListener::class);
		$context->registerEventListener(CardCreatedEvent::class, CardCreatedListener::class);
		$context->registerEventListener(CardUpdatedEvent::class, CardUpdatedListener::class);
		$context->registerEventListener(CardDeletedEvent::class, CardDeletedListener::class);
		$context->registerEventListener(AddFeaturePolicyEvent::class, AddFeaturePolicyListener::class);

		$context->registerEventListener(NodeWrittenEvent::class, NodeListener::class);
		$context->registerEventListener(BeforeNodeDeletedEvent::class, NodeListener::class);
		$context->registerEventListener(NodeRenamedEvent::class, NodeListener::class);
		$context->registerEventListener(NodeTouchedEvent::class, NodeListener::class);
		$context->registerEventListener(NodeRestoredEvent::class, NodeListener::class);

		$context->registerEventListener(ShareCreatedEvent::class, ShareListener::class);
		$context->registerEventListener(ShareDeletedEvent::class, ShareListener::class);
		$context->registerEventListener(BeforeShareDeletedEvent::class, ShareListener::class);
	}

	public function boot(IBootContext $context): void {
		// ... boot logic goes here ...
	}
}
