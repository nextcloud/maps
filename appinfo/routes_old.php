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

    array('name' => 'favorite#add_favorite', 'url' => '/api/1.0/favorite/addToFavorites', 'verb' => 'POST'),
    array('name' => 'favorite#get_favorites', 'url' => '/api/1.0/favorite/getFavorites', 'verb' => 'POST'),
    array('name' => 'favorite#remove_favorite', 'url' => '/api/1.0/favorite/removeFromFavorites', 'verb' => 'POST'),
    array('name' => 'favorite#update_favorite', 'url' => '/api/1.0/favorite/updateFavorite', 'verb' => 'POST'),
    array('name' => 'favorite#get_favorites_by_name', 'url' => '/api/1.0/favorite/getFavoritesByName', 'verb' => 'POST'),

    array('name' => 'apikey#get_key', 'url' => '/api/1.0/apikey/getKey', 'verb' => 'POST'),
    array('name' => 'apikey#add_key', 'url' => '/api/1.0/apikey/addKey', 'verb' => 'POST'),

)));
