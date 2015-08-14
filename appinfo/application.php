<?php
/**
 * ownCloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Sander Brand <brantje@gmail.com>
 * @copyright Sander Brand 2014
 */

namespace OCA\Maps\AppInfo;


use \OCP\AppFramework\App;
use \OCA\Maps\Db\CacheManager;
use \OCA\Maps\Db\DeviceMapper;
use \OCA\Maps\Db\LocationMapper;
use \OCA\Maps\Controller\PageController;
use \OCA\Maps\Controller\LocationController;


class Application extends App {


	public function __construct (array $urlParams=array()) {
		parent::__construct('maps', $urlParams);

		$container = $this->getContainer();

		/**
		 * Controllers
		 */
		$container->registerService('PageController', function($c) {
			return new PageController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('UserId'),
				$c->query('CacheManager'),
				$c->query('DeviceMapper')
			);
		});
		$container->registerService('LocationController', function($c) {
			return new LocationController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('LocationMapper'),
				$c->query('DeviceMapper'),
				$c->query('UserId')
			);
		});

		$container->registerService('CacheManager', function($c) {
			return new CacheManager(
				$c->query('ServerContainer')->getDb()
			);
		});
		$container->registerService('LocationMapper', function($c) {
			return new LocationMapper(
				$c->query('ServerContainer')->getDb()
			);
		});
		$container->registerService('DeviceMapper', function($c) {
			return new DeviceMapper(
				$c->query('ServerContainer')->getDb()
			);
		});

	}


}
