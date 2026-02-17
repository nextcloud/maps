<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OC\Files\Search;

use OCP\Files\Search\ISearchComparison;

/**
 * @psalm-import-type ParamValue from ISearchComparison
 */
class SearchComparison implements ISearchComparison, \Stringable {
	public function getType(): string
 {
 }

	public function getField(): string
 {
 }

	public function getValue(): string|int|bool|\DateTime|array
 {
 }

	/**
     * @since 28.0.0
     */
    public function getExtra(): string
 {
 }

	public function getQueryHint(string $name, $default)
 {
 }

	public function setQueryHint(string $name, $value): void
 {
 }

	public static function escapeLikeParameter(string $param): string
 {
 }

	public function __toString(): string
    {
        return '';
    }
}
