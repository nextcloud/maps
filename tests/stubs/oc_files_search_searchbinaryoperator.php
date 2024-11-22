<?php
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\Files\Search;

use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchOperator;

class SearchBinaryOperator implements ISearchBinaryOperator {
	/**
	 * SearchBinaryOperator constructor.
	 *
	 * @param string $type
	 * @param (SearchBinaryOperator|SearchComparison)[] $arguments
	 */
	public function __construct($type, array $arguments)
 {
 }

	/**
	 * @return string
	 */
	public function getType()
 {
 }

	/**
	 * @return ISearchOperator[]
	 */
	public function getArguments()
 {
 }

	/**
	 * @param ISearchOperator[] $arguments
	 * @return void
	 */
	public function setArguments(array $arguments): void
 {
 }

	public function getQueryHint(string $name, $default)
 {
 }

	public function setQueryHint(string $name, $value): void
 {
 }

	public function __toString(): string
 {
 }
}
