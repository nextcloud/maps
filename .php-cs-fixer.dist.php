<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor-bin/csfixer/vendor/autoload.php';

use Nextcloud\CodingStandard\Config;

$config = new Config();
$config
	->getFinder()
	->ignoreVCSIgnored(true)
	->notPath('tests/stubs')
	->notPath('l10n')
	->notPath('src')
	->notPath('vendor')
	->notPath('vendor-bin')
	->in(__DIR__);
return $config;
