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
        ['name' => 'page#do_echo', 'url' => '/echo', 'verb' => 'POST'],
        ['name' => 'page#openGeoLink', 'url' => '/openGeoLink/{url}', 'verb' => 'GET'],


        // utils
        ['name' => 'utils#getOptionsValues', 'url' => '/getOptionsValues', 'verb' => 'POST'],
        ['name' => 'utils#saveOptionValue', 'url' => '/saveOptionValue', 'verb' => 'POST'],
        ['name' => 'utils#setRoutingSettings', 'url' => '/setRoutingSettings', 'verb' => 'POST'],

        // photos
        ['name' => 'photos#getPhotosFromDb', 'url' => '/photos', 'verb' => 'GET'],
        ['name' => 'photos#getNonLocalizedPhotosFromDb', 'url' => '/photos/nonlocalized', 'verb' => 'GET'],
        ['name' => 'photos#placePhotos', 'url' => '/photos', 'verb' => 'POST'],
        ['name' => 'photos#resetPhotosCoords', 'url' => '/photos', 'verb' => 'DELETE'],

        // contacts
        ['name' => 'contacts#getContacts', 'url' => '/contacts', 'verb' => 'GET'],
        ['name' => 'contacts#getAllContacts', 'url' => '/contacts-all', 'verb' => 'GET'],
        ['name' => 'contacts#placeContact', 'url' => '/contacts/{bookid}/{uri}', 'verb' => 'PUT'],
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

        // favorites
        ['name' => 'favorites#getFavorites', 'url' => '/favorites', 'verb' => 'GET'],
        ['name' => 'favorites#addFavorite', 'url' => '/favorites', 'verb' => 'POST'],
        ['name' => 'favorites#editFavorite', 'url' => '/favorites/{id}', 'verb' => 'PUT'],
        ['name' => 'favorites#deleteFavorite', 'url' => '/favorites/{id}', 'verb' => 'DELETE'],
        ['name' => 'favorites#deleteFavorites', 'url' => '/favorites', 'verb' => 'DELETE'],
        ['name' => 'favorites#renameCategories', 'url' => '/favorites-category', 'verb' => 'PUT'],

        ['name' => 'favorites#exportFavorites', 'url' => '/export/favorites', 'verb' => 'POST'],
        ['name' => 'favorites#importFavorites', 'url' => '/import/favorites', 'verb' => 'POST'],

        // tracks
        ['name' => 'tracks#getTracks', 'url' => '/tracks', 'verb' => 'GET'],
        ['name' => 'tracks#getTrackFileContent', 'url' => '/tracks/{id}', 'verb' => 'GET'],
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

    ]
];
