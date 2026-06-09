<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
use Nextcloud\Rector\Set\NextcloudSets;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/lib',
		__DIR__ . '/tests/Unit',
	])
	->withSkip([
		__DIR__ . '/tests/stubs',
	])
	->withPreparedSets(
		deadCode: true,
		typeDeclarations: true,
	)->withSets([
		NextcloudSets::NEXTCLOUD_30,
	])->withPhpSets(
		php82: true,
	)->withConfiguredRule(ClassPropertyAssignToConstructorPromotionRector::class, [
		'inline_public' => true,
		'rename_property' => true,
	]);
