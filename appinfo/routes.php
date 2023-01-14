<?php
/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\Maps\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#indexMyMap', 'url' => '/m/{myMapId}', 'verb' => 'GET'],
        ['name' => 'page#do_echo', 'url' => '/echo', 'verb' => 'POST'],
        ['name' => 'page#openGeoLink', 'url' => '/openGeoLink/{url}', 'verb' => 'GET'],
        ['name' => 'public_favorite_page#sharedFavoritesCategory', 'url' => '/s/favorites/{token}', 'verb' => 'GET'],
		['name' => 'PublicPage#showShare', 'url' => '/s/{token}', 'verb' => 'GET'],
		['name' => 'PublicPage#showAuthenticate', 'url' => '/s/{token}/authenticate/{redirect}', 'verb' => 'GET'],
		['name' => 'PublicPage#authenticate', 'url' => '/s/{token}/authenticate/{redirect}', 'verb' => 'POST'],


        // utils
        ['name' => 'utils#getOptionsValues', 'url' => '/getOptionsValues', 'verb' => 'GET'],
        ['name' => 'utils#saveOptionValue', 'url' => '/saveOptionValue', 'verb' => 'POST'],
        ['name' => 'utils#setRoutingSettings', 'url' => '/setRoutingSettings', 'verb' => 'POST'],
        ['name' => 'utils#getTrafficStyle', 'url' => '/style/traffic', 'verb' => 'GET'],
		['name' => 'PublicUtils#getOptionsValues', 'url' => '/s/{token}/getOptionsValues', 'verb' => 'GET'],
		['name' => 'PublicUtils#saveOptionValue', 'url' => '/s/{token}/saveOptionValue', 'verb' => 'POST'],
		['name' => 'PublicUtils#setRoutingSettings', 'url' => '/s/{token}/setRoutingSettings', 'verb' => 'POST'],
		['name' => 'PublicUtils#getTrafficStyle', 'url' => '/s/{token}/style/traffic', 'verb' => 'GET'],

        // photos
        ['name' => 'photos#getPhotosFromDb', 'url' => '/photos', 'verb' => 'GET'],
        ['name' => 'photos#getNonLocalizedPhotosFromDb', 'url' => '/photos/nonlocalized', 'verb' => 'GET'],
        ['name' => 'photos#placePhotos', 'url' => '/photos', 'verb' => 'POST'],
        ['name' => 'photos#resetPhotosCoords', 'url' => '/photos', 'verb' => 'DELETE'],
		['name' => 'photos#clearCache', 'url' => '/photos/clearCache', 'verb' => 'GET'],

        // contacts
        ['name' => 'contacts#getContacts', 'url' => '/contacts', 'verb' => 'GET'],
        ['name' => 'contacts#searchContacts', 'url' => '/contacts-search', 'verb' => 'GET'],
        ['name' => 'contacts#placeContact', 'url' => '/contacts/{bookid}/{uri}', 'verb' => 'PUT'],
		['name' => 'contacts#addContactToMap', 'url' => '/contacts/{bookid}/{uri}/add-to-map/', 'verb' => 'PUT'],
        ['name' => 'contacts#deleteContactAddress', 'url' => '/contacts/{bookid}/{uri}', 'verb' => 'DELETE'],
        ['name' => 'contacts#getContactLetterAvatar', 'url' => '/contacts-avatar', 'verb' => 'GET'],

        // routing
        ['name' => 'routing#exportRoute', 'url' => '/exportRoute', 'verb' => 'POST'],

        // favorites API
        [
            'name'         => 'favorites_api#preflighted_cors',
            'url'          => '/api/1.0/favorites{path}',
            'verb'         => 'OPTIONS',
            'requirements' => ['path' => '.+']
        ],
        ['name' => 'favorites_api#getFavorites', 'url' => '/api/{apiversion}/favorites', 'verb' => 'GET'],
        ['name' => 'favorites_api#addFavorite', 'url' => '/api/{apiversion}/favorites', 'verb' => 'POST'],
        ['name' => 'favorites_api#editFavorite', 'url' => '/api/{apiversion}/favorites/{id}', 'verb' => 'PUT'],
        ['name' => 'favorites_api#deleteFavorite', 'url' => '/api/{apiversion}/favorites/{id}', 'verb' => 'DELETE'],

        // public favorites API
        [
            'name'         => 'favorites_api#preflighted_cors',
            'url'          => '/api/1.0/public/favorites{path}',
            'verb'         => 'OPTIONS',
            'requirements' => ['path' => '.+']
        ],
        ['name' => 'public_favorites_api#getFavorites', 'url' => '/api/1.0/public/{token}/favorites', 'verb' => 'GET'],
//        ['name' => 'public_favorites_api#addFavorite', 'url' => '/api/1.0/public/{token}/favorites', 'verb' => 'POST'],
//        ['name' => 'public_favorites_api#editFavorite', 'url' => '/api/1.0/public/{token}/favorites/{id}', 'verb' => 'PUT'],
//        ['name' => 'public_favorites_api#deleteFavorite', 'url' => '/api/1.0/public/{token}/favorites/{id}', 'verb' => 'DELETE'],

        // favorites
        ['name' => 'favorites#getFavorites', 'url' => '/favorites', 'verb' => 'GET'],
        ['name' => 'favorites#addFavorite', 'url' => '/favorite', 'verb' => 'POST'],
		['name' => 'favorites#addFavorites', 'url' => '/favorites', 'verb' => 'POST'],
        ['name' => 'favorites#editFavorite', 'url' => '/favorites/{id}', 'verb' => 'PUT'],
        ['name' => 'favorites#deleteFavorite', 'url' => '/favorites/{id}', 'verb' => 'DELETE'],
        ['name' => 'favorites#deleteFavorites', 'url' => '/favorites', 'verb' => 'DELETE'],

        // favorite categories
        ['name' => 'favorites#renameCategories', 'url' => '/favorites-category', 'verb' => 'PUT'],
        ['name' => 'favorites#getSharedCategories', 'url' => '/favorites-category/shared', 'verb' => 'GET'],
        ['name' => 'favorites#shareCategory', 'url' => '/favorites-category/{category}/share', 'verb' => 'POST'],
        ['name' => 'favorites#unShareCategory', 'url' => '/favorites-category/{category}/un-share', 'verb' => 'POST'],
		['name' => 'favorites#addShareCategoryToMap', 'url' => '/favorites-category/{category}/add-to-map/{targetMapId}', 'verb' => 'PUT'],
		['name' => 'favorites#removeShareCategoryFromMap', 'url' => '/favorites-category/{category}/', 'verb' => 'DELETE'],

        ['name' => 'favorites#exportFavorites', 'url' => '/export/favorites', 'verb' => 'POST'],
        ['name' => 'favorites#importFavorites', 'url' => '/import/favorites', 'verb' => 'POST'],

        // tracks
        ['name' => 'tracks#getTracks', 'url' => '/tracks', 'verb' => 'GET'],
        ['name' => 'tracks#getTrackFileContent', 'url' => '/tracks/{id}', 'verb' => 'GET'],
		['name' => 'tracks#getTrackContentByFileId', 'url' => '/tracks/file/{id}', 'verb' => 'GET'],
        ['name' => 'tracks#editTrack', 'url' => '/tracks/{id}', 'verb' => 'PUT'],

        // devices API
        [
            'name'         => 'devices_api#preflighted_cors',
            'url'          => '/api/1.0/devices{path}',
            'verb'         => 'OPTIONS',
            'requirements' => ['path' => '.+']
        ],
        ['name' => 'devices_api#getDevices', 'url' => '/api/{apiversion}/devices', 'verb' => 'GET'],
        ['name' => 'devices_api#getDevicePoints', 'url' => '/api/{apiversion}/devices/{id}', 'verb' => 'GET'],
        ['name' => 'devices_api#addDevicePoint', 'url' => '/api/{apiversion}/devices', 'verb' => 'POST'],
        ['name' => 'devices_api#editDevice', 'url' => '/api/{apiversion}/devices/{id}', 'verb' => 'PUT'],
        ['name' => 'devices_api#deleteDevice', 'url' => '/api/{apiversion}/devices/{id}', 'verb' => 'DELETE'],

        // devices
        ['name' => 'devices#getDevices', 'url' => '/devices', 'verb' => 'GET'],
        ['name' => 'devices#getDevicePoints', 'url' => '/devices/{id}', 'verb' => 'GET'],
        ['name' => 'devices#addDevicePoint', 'url' => '/devices', 'verb' => 'POST'],
        ['name' => 'devices#editDevice', 'url' => '/devices/{id}', 'verb' => 'PUT'],
        ['name' => 'devices#deleteDevice', 'url' => '/devices/{id}', 'verb' => 'DELETE'],
        ['name' => 'devices#exportDevices', 'url' => '/export/devices', 'verb' => 'POST'],
        ['name' => 'devices#importDevices', 'url' => '/import/devices', 'verb' => 'POST'],

        //MyMaps
        ['name' => 'my_maps#getMyMaps', 'url' => '/maps', 'verb' => 'GET'],
        ['name' => 'my_maps#addMyMap' , 'url' => '/maps', 'verb' => 'POST'],
        ['name' => 'my_maps#updateMyMap' , 'url' => '/maps/{id}', 'verb' => 'PUT'],
        ['name' => 'my_maps#deleteMyMap' , 'url' => '/maps/{id}', 'verb' => 'DELETE'],
    ]
];
