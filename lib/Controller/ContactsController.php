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

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\ILogger;
use OCP\AppFramework\Controller;
use OCP\Contacts\IManager;
use OCA\Maps\Service\AddressService;

class ContactsController extends Controller {
    private $userId;
    private $logger;
    private $contactsManager;
    private $addressService;

    public function __construct($AppName, ILogger $logger, IRequest $request, IManager $contactsManager, AddressService $addressService, $UserId){
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->userId = $UserId;
        $this->contactsManager = $contactsManager;
        $this->addressService = $addressService;
    }

    /**
     * get contacts with coordinates
     * @NoAdminRequired
     */
    public function getContacts() {
        $contacts = $this->contactsManager->search('', ['GEO','ADR'], ['types'=>false]);
        $result = [];
        $userid = trim($this->userId);
        foreach ($contacts as $c) {
            $uid = trim($c['UID']);
            if (strcmp($c['URI'], 'Database:'.$c['UID'].'.vcf') !== 0 or
                strcmp($uid, $userid) === 0
            ) {
                //If the contact has a geo attibute use this, otherwise try to get it from the address
                if(key_exists('GEO',$c)) {
                    $geo = $c['GEO'];
                } else {
                    $geo = $this->addressService->addressToGeo($c["ADR"]);
                }
                if(strlen($geo)>1){
                    array_push($result, [
                        'FN'=>$c['FN'],
                        'URI'=>$c['URI'],
                        'UID'=>$c['UID'],
                        'BOOKID'=>$c['addressbook-key'],
                        'GEO'=>$geo
                    ]);
                }
            }
        }
        return new DataResponse($result);
    }

    /**
     * get all contacts
     * @NoAdminRequired
     */
    public function getAllContacts() {
        $contacts = $this->contactsManager->search('', ['FN'], ['types'=>false]);
        $result = [];
        $userid = trim($this->userId);
        foreach ($contacts as $c) {
            $uid = trim($c['UID']);
            if (strcmp($c['URI'], 'Database:'.$c['UID'].'.vcf') !== 0 or
                strcmp($uid, $userid) === 0
            ) {
                array_push($result, [
                    'FN'=>$c['FN'],
                    'URI'=>$c['URI'],
                    'UID'=>$c['UID'],
                    'BOOKID'=>$c['addressbook-key']
                ]);
            }
        }
        return new DataResponse($result);
    }

    /**
     * @NoAdminRequired
     */
    public function placeContact($bookid, $uri, $uid, $lat, $lng) {
        // do not edit 'user' contact except myself
        if (strcmp($uri, 'Database:'.$uid.'.vcf') === 0 and
            strcmp($uid, $this.userId) !== 0
        ) {
            return new DataResponse('Can\'t edit users', 400);
        }
        else {
            // TODO check addressbook permissions
            // it's currently possible to place a contact from an addressbook shared with readonly permissions...
            if ($lat !== null && $lng !== null) {
                $result = $this->contactsManager->createOrUpdate(['URI'=>$uri, 'GEO'=>$lat.';'.$lng], $bookid);
            }
            else {
                // TODO find out how to remove a property
                // following does not work properly
                $result = $this->contactsManager->createOrUpdate(['URI'=>$uri, 'GEO'=>null], $bookid);
            }
            return new DataResponse('EDITED');
        }
    }

}
