<?php
/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2019
 */

namespace OCA\Maps\Controller;

use OCP\App\IAppManager;

use OCP\IURLGenerator;
use OCP\IConfig;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

class UtilsController extends Controller {


    private $userId;
    private $config;
    private $dbtype;

    public function __construct($AppName,
                                IRequest $request,
                                IConfig $config,
                                IAppManager $appManager,
                                $UserId){
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        // IConfig object
        $this->config = $config;
    }

	/**
	 * Delete user options
	 *
	 * @NoAdminRequired
	 * @return DataResponse
	 */
    public function deleteOptionsValues(): DataResponse {
        $keys = $this->config->getUserKeys($this->userId, 'maps');
        foreach ($keys as $key) {
            $this->config->deleteUserValue($this->userId, 'maps', $key);
        }
        return new DataResponse(['done'=>1]);
    }

	/**
	 * Save options values to the DB for current user
	 *
	 * @NoAdminRequired
	 * @param $options
	 * @return DataResponse
	 * @throws \OCP\PreConditionNotMetException
	 */
    public function saveOptionValue($options): DataResponse {
        foreach ($options as $key => $value) {
            $this->config->setUserValue($this->userId, 'maps', $key, $value);
        }
        return new DataResponse(['done'=>1]);
    }

	/**
	 * get options values from the config for current user
	 *
	 * @NoAdminRequired
	 * @return DataResponse
	 */
    public function getOptionsValues(): DataResponse {
        $ov = array();

        // get all user values
        $keys = $this->config->getUserKeys($this->userId, 'maps');
        foreach ($keys as $key) {
            $value = $this->config->getUserValue($this->userId, 'maps', $key);
            $ov[$key] = $value;
        }

        // get routing-specific admin settings values
        $settingsKeys = [
            'osrmCarURL',
            'osrmBikeURL',
            'osrmFootURL',
            'osrmDEMO',
            'graphhopperAPIKEY',
            'mapboxAPIKEY',
            'graphhopperURL'
        ];
        foreach ($settingsKeys as $k) {
            $v = $this->config->getAppValue('maps', $k);
            $ov[$k] = $v;
        }
        return new DataResponse(['values'=>$ov]);
    }

	/**
	 * set routing settings
	 *
	 * @param $values
	 * @return DataResponse
	 */
    public function setRoutingSettings($values): DataResponse {
        $acceptedKeys = [
            'osrmCarURL',
            'osrmBikeURL',
            'osrmFootURL',
            'osrmDEMO',
            'graphhopperAPIKEY',
            'mapboxAPIKEY',
            'graphhopperURL'
        ];
        foreach ($values as $k=>$v) {
            if (in_array($k, $acceptedKeys)) {
                $this->config->setAppValue('maps', $k, $v);
            }
        }
        $response = new DataResponse('DONE');
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * get content of mapbox traffic style
     * @NoAdminRequired
     * @NoCSRFRequired
     *
     * @return DataResponse
     */
    public function getTrafficStyle(): DataResponse {
        $style = [
            'version' => 8,
            'name' => 'Mapbox Traffic tileset v1',
            'sources' => [
                'mapbox-traffic' => [
                    'url' => 'mapbox://mapbox.mapbox-traffic-v1',
                    'type' => 'vector'
                ]
            ],
            'layers' => [
                [
                    'id' => 'traffic',
                    'source' => 'mapbox-traffic',
                    'source-layer' => 'traffic',
                    'type' => 'line',
                    'paint' => [
                        'line-width' => 2.0,
                        'line-color' => [
                            'case',
                            [
                                '==',
                                'low',
                                [
                                    'get',
                                    'congestion'
                                ]
                            ],
                            '#00ff00',
                            [
                                '==',
                                'moderate',
                                [
                                    'get',
                                    'congestion'
                                ]
                            ],
                            '#ffad00',
                            [
                                '==',
                                'heavy',
                                [
                                    'get',
                                    'congestion'
                                ]
                            ],
                            '#ff0000',
                            [
                                '==',
                                'severe',
                                [
                                    'get',
                                    'congestion'
                                ]
                            ],
                            '#b43b71',
                            '#000000'
                        ]
                    ]
                ]
            ]
        ];
        return new DataResponse($style);
    }
}
