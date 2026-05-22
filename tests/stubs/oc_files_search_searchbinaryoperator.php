<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\Files\Search;

use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchOperator;

class SearchBinaryOperator implements ISearchBinaryOperator, \Stringable {
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
        return '';
    }
}
