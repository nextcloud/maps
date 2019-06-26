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
use \OCP\DB\QueryBuilder\IQueryBuilder;
use \OCA\DAV\CardDAV\CardDavBackend;
use \Sabre\VObject\Property\Text;
use \Sabre\VObject\Reader;

class ContactsController extends Controller {
    private $userId;
    private $logger;
    private $contactsManager;
    private $addressService;
    private $dbconnection;
    private $qb;
    private $cdBackend;

    public function __construct($AppName, ILogger $logger, IRequest $request, IManager $contactsManager, AddressService $addressService, $UserId, CardDavBackend $cdBackend){
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->userId = $UserId;
        $this->contactsManager = $contactsManager;
        $this->addressService = $addressService;
        $this->dbconnection = \OC::$server->getDatabaseConnection();
        $this->qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();
        $this->cdBackend = $cdBackend;
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
    public function placeContact($bookid, $uri, $uid, $lat, $lng, $house_number, $road, $postcode, $town, $state, $country) {
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
                //$result = $this->contactsManager->createOrUpdate(['URI'=>$uri, 'GEO'=>$lat.';'.$lng], $bookid);
                $stringAddress = ';;'.$house_number.' '.$road.';'.$town.';'.$state.';'.$postcode.';'.$country;
                //$stringAddress = $house_number.' '.$road.' '.$postcode.' '.$town.' '.$state.' '.$country;
                // set the coordinates in the DB
                $this->setInternalContactAddress($lat, $lng, $stringAddress);
                // set the address in the vcard
                //$result = $this->contactsManager->createOrUpdate(['URI'=>$uri, 'ADR'=>$stringAddress], $bookid);
                $card = $this->cdBackend->getContact($bookid, $uri);
                if ($card) {
                    $vcard = Reader::read($card['carddata']);;
                    $vcard->add(new Text($vcard, 'ADR', ['', '', $house_number.' '.$road, $town, $state, $postcode, $country], ['TYPE'=>'HOME']));
                    $this->cdBackend->updateCard($bookid, $uri, $vcard->serialize());
                }
            }
            else {
                // TODO find out how to remove a property
                // following does not work properly
                $result = $this->contactsManager->createOrUpdate(['URI'=>$uri, 'GEO'=>null], $bookid);
            }
            return new DataResponse('EDITED');
        }
    }

    private function setInternalContactAddress($lat, $lng, $adr) {
        $adr_norm = strtolower(preg_replace('/\s+/', '', $adr));

        $this->qb->select('id')
             ->from('maps_address_geo')
             ->where($this->qb->expr()->eq('adr_norm', $this->qb->createNamedParameter($adr_norm, IQueryBuilder::PARAM_STR)))
             ->setMaxResults($max);
        $req=$this->qb->execute();
        $result = $req->fetchAll();
        $req->closeCursor();
        $this->qb = $this->qb->resetQueryParts();
        if ($result and count($result) > 0) {
            $id = $result[0]['id'];
            $this->qb->update('maps_address_geo')
                ->set('lat', $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR))
                ->set('lng', $qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR))
                ->set('looked_up', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
                ->where($this->qb->expr()->eq('id', $this->qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)));
            $req=$this->qb->execute();
            $qb = $this->qb->resetQueryParts();
        }
        else {
            $this->qb->insert('maps_address_geo')
                ->values([
                    'adr'=>$this->qb->createNamedParameter($adr, IQueryBuilder::PARAM_STR),
                    'adr_norm'=>$this->qb->createNamedParameter($adr_norm, IQueryBuilder::PARAM_STR),
                    'lat'=>$this->qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR),
                    'lng'=>$this->qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR),
                    'looked_up'=>$this->qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
                ]);
            $req = $this->qb->execute();
            $id = $this->qb->getLastInsertId();
            $qb = $this->qb->resetQueryParts();
        }
    }

}
