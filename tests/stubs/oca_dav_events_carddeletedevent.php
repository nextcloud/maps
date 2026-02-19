<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\DAV\Events;

use OCP\EventDispatcher\Event;

/**
 * Class CardDeletedEvent
 *
 * @package OCA\DAV\Events
 * @since 20.0.0
 */
class CardDeletedEvent extends Event {

	/**
     * @since 20.0.0
     */
    public function getAddressBookId(): int
 {
 }

	/**
     * @since 20.0.0
     */
    public function getAddressBookData(): array
 {
 }

	/**
     * @since 20.0.0
     */
    public function getShares(): array
 {
 }

	/**
     * @since 20.0.0
     */
    public function getCardData(): array
 {
 }
}
