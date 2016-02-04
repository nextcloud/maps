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


use OC\AppFramework\Utility\SimpleContainer;
use \OCP\AppFramework\App;
use \OCA\Maps\Db\CacheManager;
use \OCA\Maps\Db\DeviceMapper;
use \OCA\Maps\Db\LocationMapper;
use \OCA\Maps\Db\FavoriteMapper;
use \OCA\Maps\Db\ApiKeyMapper;
use \OCA\Maps\Controller\PageController;
use \OCA\Maps\Controller\LocationController;
use \OCA\Maps\Controller\FavoriteController;


class Application extends App {


	public function __construct (array $urlParams=array()) {
		parent::__construct('maps', $urlParams);

		$container = $this->getContainer();

		/**
		 * Controllers
		 */
		$container->registerService('PageController', function($c) {
			/** @var SimpleContainer $c */
			return new PageController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('UserId'),
				$c->query('CacheManager'),
				$c->query('DeviceMapper'),
				$c->query('ApiKeyMapper')
			);
		});
		$container->registerService('LocationController', function($c) {
			/** @var SimpleContainer $c */
			return new LocationController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('LocationMapper'),
				$c->query('DeviceMapper'),
				$c->query('UserId')
			);
		});
		$container->registerService('FavoriteController', function($c) {
			/** @var SimpleContainer $c */
			return new FavoriteController(
				$c->query('AppName'),
				$c->query('Request'),
				$c->query('FavoriteMapper'),
				$c->query('UserId')
			);
		});

		$server = $container->getServer();
		$container->registerService('CacheManager', function($c) use ($server) {
			/** @var SimpleContainer $c */
			return new CacheManager(
				$server->getDatabaseConnection()
			);
		});
		$container->registerService('LocationMapper', function($c) use ($server) {
			/** @var SimpleContainer $c */
			return new LocationMapper(
				$server->getDb()
			);
		});
		$container->registerService('DeviceMapper', function($c) use ($server) {
			/** @var SimpleContainer $c */
			return new DeviceMapper(
				$server->getDb()
			);
		});
		$container->registerService('FavoriteMapper', function($c) use ($server) {
			/** @var SimpleContainer $c */
			return new FavoriteMapper(
				$server->getDb()
			);
		});
		$container->registerService('ApiKeyMapper', function($c) use ($server) {
			/** @var SimpleContainer $c */
			return new ApiKeyMapper(
				$server->getDb()
			);
		});

	}


}
