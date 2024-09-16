<?php
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
	 * SearchQuery constructor.
	 *
	 * @param ISearchOperator $searchOperation
	 * @param int $limit
	 * @param int $offset
	 * @param array $order
	 * @param ?IUser $user
	 * @param bool $limitToHome
	 */
	public function __construct(ISearchOperator $searchOperation, int $limit, int $offset, array $order, ?IUser $user = null, bool $limitToHome = false)
 {
 }

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
