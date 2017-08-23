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


class Application extends App {
	public function __construct (array $urlParams=array()) {
		parent::__construct('maps', $urlParams);

		$container = $this->getContainer();
	}
}
