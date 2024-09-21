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
	 * CardDeletedEvent constructor.
	 *
	 * @param int $addressBookId
	 * @param array $addressBookData
	 * @param array $shares
	 * @param array $cardData
	 * @since 20.0.0
	 */
	public function __construct(int $addressBookId, array $addressBookData, array $shares, array $cardData)
 {
 }

	/**
	 * @return int
	 * @since 20.0.0
	 */
	public function getAddressBookId(): int
 {
 }

	/**
	 * @return array
	 * @since 20.0.0
	 */
	public function getAddressBookData(): array
 {
 }

	/**
	 * @return array
	 * @since 20.0.0
	 */
	public function getShares(): array
 {
 }

	/**
	 * @return array
	 * @since 20.0.0
	 */
	public function getCardData(): array
 {
 }
}
