<?php

declare(strict_types=1);

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

use OCP\App\IAppManager;
use OCP\Server;

require_once __DIR__ . '/../../../tests/bootstrap.php';
require_once __DIR__ . '/../../../lib/base.php';
require_once __DIR__ . '/../vendor/autoload.php';

Server::get(IAppManager::class)->loadApp('maps');

OC_Hook::clear();
