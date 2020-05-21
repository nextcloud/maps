<?php
/**
 * Nextcloud - Maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @author Paul Schwörer <hello@paulschwoerer.de>
 * @copyright Julien Veyssier 2019
 * @copyright Paul Schwörer 2019
 */

namespace OCA\Maps\Controller;

use OCA\Maps\Service\MyMapsService;
use OCP\Files\NotFoundException;
use OCA\Maps\DB\FavoriteShareMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class MyMapsController extends Controller {


    /* @var MyMapsService */
    private $myMapsService;

    public function __construct($AppName, IRequest $request, MyMapsService $myMapsService) {
        parent::__construct($AppName, $request);
        $this->myMapsService = $myMapsService;
    }

    /**
     * @NoAdminRequired
     */
    public function getMyMaps() {
        $myMaps = $this->myMapsService->getAllMyMaps();
        return new DataResponse($myMaps);
    }
}
