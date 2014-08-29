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
use \OCA\Maps\Db\LocationManager;
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
				$c->query('LocationManager')
			);
		});
		$container->registerService('LocationController', function($c) {
			return new LocationController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('LocationManager'),
				$c->query('UserId')
			);
		});
	
		$container->registerService('CacheManager', function($c) {
			return new CacheManager(
				$c->query('ServerContainer')->getDb()
			);
		});
		$container->registerService('LocationManager', function($c) {
			return new LocationManager(
				$c->query('ServerContainer')->getDb()
			);
		});

		/**
		 * Core
		 */
		$container->registerService('UserId', function($c) {
			return \OCP\User::getUser();
		});		
		$container->registerService('Db', function() {
			return new Db();
		});
		
	}


}