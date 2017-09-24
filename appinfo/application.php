<?php
/**
 * ownCloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sander Brand <brantje@gmail.com>, Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 * @copyright Sander Brand 2014, Vinzenz Rosenkranz 2016, 2017
 */

namespace OCA\Maps\AppInfo;


use OC\AppFramework\Utility\SimpleContainer;
use \OCP\AppFramework\App;
use OCA\Maps\Controller\PageController;
use OCA\Maps\Hook\FileHooks;
use OCA\Maps\Service\PhotofilesService;


class Application extends App {
	public function __construct (array $urlParams=array()) {
		parent::__construct('maps', $urlParams);

		$container = $this->getContainer();

		$this->getContainer()->registerService('FileHooks', function($c) {
			return new FileHooks(
				$c->query('ServerContainer')->getRootFolder(),
				\OC::$server->query(PhotofilesService::class),
				$c->query('ServerContainer')->getLogger(),
				$c->query('AppName')
			);
		});

		$this->getContainer()->query('FileHooks')->register();
	}

}
