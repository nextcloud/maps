<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\Files\Search;

use OCP\Files\Search\ISearchOperator;
use OCP\Files\Search\ISearchOrder;
use OCP\Files\Search\ISearchQuery;
use OCP\IUser;

class SearchQuery implements ISearchQuery {
	/**
	 * @return ISearchOperator
	 */
	public function getSearchOperation()
 {
 }

	/**
	 * @return int
	 */
	public function getLimit()
 {
 }

	/**
	 * @return int
	 */
	public function getOffset()
 {
 }

	/**
	 * @return ISearchOrder[]
	 */
	public function getOrder()
 {
 }

	/**
	 * @return ?IUser
	 */
	public function getUser()
 {
 }

	public function limitToHome(): bool
 {
 }
}
