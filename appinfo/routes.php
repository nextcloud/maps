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

       //photos
       ['name' => 'photos#getPhotosFromDb', 'url' => '/photos', 'verb' => 'GET'],

       // favorites
       [
            'name'         => 'favorites#preflighted_cors',
            'url'          => '/api/1.0/{path}',
            'verb'         => 'OPTIONS',
            'requirements' => ['path' => '.+']
        ],
        ['name' => 'favorites#getFavorites', 'url' => '/api/{apiversion}/favorites', 'verb' => 'GET'],
    ]
];
