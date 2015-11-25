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

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
$application = new Application();

$application->registerRoutes($this, array('routes' => array(
	array('name' => 'page#index', 'url' => '/', 'verb' => 'GET'),
    array('name' => 'page#do_proxy', 'url' => '/router', 'verb' => 'GET'),
    array('name' => 'page#getlayer', 'url' => '/layer', 'verb' => 'GET'),
    array('name' => 'page#adresslookup', 'url' => '/adresslookup', 'verb' => 'GET'),
    array('name' => 'page#geodecode', 'url' => '/geodecode', 'verb' => 'GET'),
    array('name' => 'page#search', 'url' => '/search', 'verb' => 'GET'),

    array('name' => 'location#update', 'url' => '/api/1.0/location/update', 'verb' => 'GET'),
    array('name' => 'location#add_device', 'url' => '/api/1.0/location/adddevice', 'verb' => 'POST'),
    array('name' => 'location#remove_device', 'url' => '/api/1.0/location/removedevice', 'verb' => 'POST'),
    array('name' => 'location#load_devices', 'url' => '/api/1.0/location/loadDevices', 'verb' => 'GET'),
    array('name' => 'location#load_locations', 'url' => '/api/1.0/location/loadLocations', 'verb' => 'GET'),
    array('name' => 'favorite#add_favorite', 'url' => '/api/1.0/favorite/addToFavorites', 'verb' => 'POST'),
    array('name' => 'favorite#get_favorites', 'url' => '/api/1.0/favorite/getFavorites', 'verb' => 'POST'),

)));
