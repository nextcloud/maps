<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
use Nextcloud\Rector\Set\NextcloudSets;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/lib',
		__DIR__ . '/tests',
	])
	->withSkip([
		__DIR__ . '/tests/stub.php',
		__DIR__ . '/tests/stubs',
	])
	->withImportNames(
		importShortClasses: false,
	)
	->withPreparedSets(
		deadCode: true,
		codeQuality: true,
		codingStyle: true,
		typeDeclarations: true,
		typeDeclarationDocblocks: true,
		privatization: true,
		instanceOf: true,
		earlyReturn: true,
		rectorPreset: true,
		phpunitCodeQuality: true,
		doctrineCodeQuality: true,
		symfonyCodeQuality: true,
		symfonyConfigs: true,
	)->withPhpSets(
		php81: true,
	)->withConfiguredRule(ClassPropertyAssignToConstructorPromotionRector::class, [
		'inline_public' => true,
		'rename_property' => true,
	])
	->withSets([
		NextcloudSets::NEXTCLOUD_30,
		PHPUnitSetList::PHPUNIT_100,
	])
	->withRules([
		SafeDeclareStrictTypesRector::class,
	]);
