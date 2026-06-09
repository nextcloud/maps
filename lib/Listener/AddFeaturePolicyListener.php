<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH
 * SPDX-FileContributor: Carl Schwan
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Maps\Listener;

use OCP\AppFramework\Http\EmptyFeaturePolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Security\FeaturePolicy\AddFeaturePolicyEvent;

/**
 * @template-implements IEventListener<AddFeaturePolicyEvent>
 */
class AddFeaturePolicyListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof AddFeaturePolicyEvent) {
			return;
		}

		$fp = new EmptyFeaturePolicy();
		$fp->addAllowedGeoLocationDomain('\'self\'');
		$event->addPolicy($fp);
	}
}
