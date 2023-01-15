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

use League\Flysystem\FileNotFoundException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\App\IAppManager;

use OCP\IURLGenerator;
use OCP\IConfig;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\Lock\LockedException;
use OCP\Share\Exceptions\ShareNotFound;
use PhpParser\JsonDecoder;

class PublicUtilsController extends PublicPageController {


	private $config;
	private $root;

    public function __construct($AppName,
                                IRequest $request,
                                IConfig $config,
                                IAppManager $appManager,
								IRootFolder $root){
        parent::__construct($AppName, $request);
		$this->root = $root;
        // IConfig object
        $this->config = $config;
    }

	/**
	 * Validate the permissions of the share
	 *
	 * @param Share\IShare $share
	 * @return bool
	 */
	private function validateShare(\OCP\Share\IShare $share) {
		// If the owner is disabled no access to the link is granted
		$owner = $this->userManager->get($share->getShareOwner());
		if ($owner === null || !$owner->isEnabled()) {
			return false;
		}

		// If the initiator of the share is disabled no access is granted
		$initiator = $this->userManager->get($share->getSharedBy());
		if ($initiator === null || !$initiator->isEnabled()) {
			return false;
		}

		return $share->getNode()->isReadable() && $share->getNode()->isShareable();
	}

	/**
	 * @return \OCP\Files\File|\OCP\Files\Folder
	 * @throws NotFoundException
	 */
	private function getShareNode() {
		\OC_User::setIncognitoMode(true);

		// Check whether share exists
		try {
			$share = $this->shareManager->getShareByToken($this->getToken());
		} catch (ShareNotFound $e) {
			// The share does not exists, we do not emit an ShareLinkAccessedEvent
			throw new NotFoundException();
		}

		if (!$this->validateShare($share)) {
			throw new NotFoundException();
		}

		return $share->getNode();
	}

	/**
	 * Delete user options
	 *
	 * @PublicPage
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
	 * @PublicPage
	 * @param $options
	 * @return DataResponse
	 * @throws \OCP\PreConditionNotMetException
	 */
    public function saveOptionValue($options, $myMapId=null): DataResponse  {
        if( is_null($myMapId) || $myMapId==="") {
            foreach ($options as $key => $value) {
                $this->config->setUserValue($this->userId, 'maps', $key, $value);
            }
        } else {
			$userFolder = $this->root->getUserFolder($this->userId);
            $folders = $userFolder->getById($myMapId);
            $folder = array_shift($folders);
            try {
                $file=$folder->get(".index.maps");
            } catch (NotFoundException $e) {
                $file=$folder->newFile(".index.maps", $content = "{}");
            }
            try {
                $ov = json_decode($file->getContent(),true, 512);
                foreach ($options as $key => $value) {
                    $ov[$key] = $value;
                }
                $file->putContent(json_encode($ov, JSON_PRETTY_PRINT));
            } catch (LockedException $e){
                return new DataResponse("File is locked", 500);
            }
        }
        return new DataResponse(['done'=>1]);
    }

	/**
	 * get options values from the config for current user
	 *
	 * @PublicPage
	 * @return DataResponse
	 */
    public function getOptionsValues($myMapId=null): DataResponse {
        $ov = array();

		$folder = $this->getShareNode();
		try {
			$file=$folder->get(".index.maps");
		} catch (NotFoundException $e) {
			$file=$folder->newFile(".index.maps", $content = "{}");
		}
		$ov = json_decode($file->getContent(),true, 512);
		$ov['isCreatable'] = $folder->isCreatable();
		//We can delete the map by deleting the folder or the .index.maps file
		$ov['isDeletable'] = $folder->isDeletable() || $file->isDeletable();
		// Maps content can be read mostly from the folder
		$ov['isReadable'] = $folder->isReadable();
		//Saving maps information in the file
		$ov['isUpdateable'] = $file->isUpdateable();
		// Share map by sharing the folder
		$ov['isShareable'] = $folder->isShareable();


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
     * @PublicPage
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
