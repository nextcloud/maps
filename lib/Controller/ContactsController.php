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
use OCP\IAvatarManager;
use OCP\AppFramework\Http\DataDisplayResponse;
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
    private $avatarManager;

    public function __construct($AppName, ILogger $logger, IRequest $request,
                                IManager $contactsManager, AddressService $addressService,
                                $UserId, CardDavBackend $cdBackend, IAvatarManager $avatarManager){
        parent::__construct($AppName, $request);
        $this->logger = $logger;
        $this->userId = $UserId;
        $this->avatarManager = $avatarManager;
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
        $addressBooks = $this->contactsManager->getUserAddressBooks();
        $result = [];
        $userid = trim($this->userId);
        foreach ($contacts as $c) {
            $addressBookUri = $addressBooks[$c['addressbook-key']]->getUri();
            $uid = trim($c['UID']);
            // we don't give users, just contacts
            if (strcmp($c['URI'], 'Database:'.$c['UID'].'.vcf') !== 0) {
                // if the contact has a geo attibute use it
                if (key_exists('GEO', $c)) {
                    $geo = $c['GEO'];
                    if(strlen($geo) > 1){
                        array_push($result, [
                            'FN'=>$c['FN'],
                            'URI'=>$c['URI'],
                            'UID'=>$c['UID'],
                            'ADR'=>'',
                            'ADRTYPE'=>'',
                            'HAS_PHOTO'=>($c['PHOTO'] !== null),
                            'BOOKID'=>$c['addressbook-key'],
                            'BOOKURI'=>$addressBookUri,
                            'GEO'=>$geo
                        ]);
                    }
                }
                // anyway try to get it from the address
                $card = $this->cdBackend->getContact($c['addressbook-key'], $c['URI']);
                if ($card) {
                    $vcard = Reader::read($card['carddata']);;
                    //$adrs = $vcard->get('ADR');
                    //error_log('NB '.count($vcard->ADR));
                    foreach ($vcard->ADR as $adr) {
                        $geo = $this->addressService->addressToGeo($adr->getValue());
                        //var_dump($adr->parameters()['TYPE']->getValue());
                        $adrtype = '';
                        if (isset($adr->parameters()['TYPE'])) {
                            $adrtype = $adr->parameters()['TYPE']->getValue();
                        }
                        if(strlen($geo) > 1){
                            array_push($result, [
                                'FN'=>$c['FN'],
                                'URI'=>$c['URI'],
                                'UID'=>$c['UID'],
                                'ADR'=>$adr->getValue(),
                                'ADRTYPE'=>$adrtype,
                                'HAS_PHOTO'=>($c['PHOTO'] !== null),
                                'BOOKID'=>$c['addressbook-key'],
                                'BOOKURI'=>$addressBookUri,
                                'GEO'=>$geo
                            ]);
                        }
                    }
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
            // we don't give users, just contacts
            if (strcmp($c['URI'], 'Database:'.$c['UID'].'.vcf') !== 0) {
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
    public function placeContact($bookid, $uri, $uid, $lat, $lng, $attraction, $house_number, $road, $postcode, $city, $state, $country, $type) {
        // do not edit 'user' contact even myself
        if (strcmp($uri, 'Database:'.$uid.'.vcf') === 0) {
            return new DataResponse('Can\'t edit users', 400);
        }
        else {
            // TODO check addressbook permissions
            // it's currently possible to place a contact from an addressbook shared with readonly permissions...
            if ($lat !== null && $lng !== null) {
                // we set the geo tag
                if (!$attraction && !$house_number && !$road && !$postcode && !$city && !$state && !$country) {
                    $result = $this->contactsManager->createOrUpdate(['URI'=>$uri, 'GEO'=>$lat.';'.$lng], $bookid);
                }
                // we set the address
                else {
                    $stringAddress = ';;'.$attraction.' '.$house_number.' '.$road.';'.$city.';'.$state.';'.$postcode.';'.$country;
                    // set the coordinates in the DB
                    $lat = floatval($lat);
                    $lng = floatval($lng);
                    $this->setAddressCoordinates($lat, $lng, $stringAddress);
                    // set the address in the vcard
                    $card = $this->cdBackend->getContact($bookid, $uri);
                    if ($card) {
                        $vcard = Reader::read($card['carddata']);;
                        $vcard->add(new Text($vcard, 'ADR', ['', '', $attraction.' '.$house_number.' '.$road, $city, $state, $postcode, $country], ['TYPE'=>$type]));
                        $this->cdBackend->updateCard($bookid, $uri, $vcard->serialize());
                    }
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

    private function setAddressCoordinates($lat, $lng, $adr) {
        $qb = $this->qb;
        $adr_norm = strtolower(preg_replace('/\s+/', '', $adr));

        $qb->select('id')
             ->from('maps_address_geo')
             ->where($qb->expr()->eq('adr_norm', $qb->createNamedParameter($adr_norm, IQueryBuilder::PARAM_STR)))
             ->setMaxResults($max);
        $req = $qb->execute();
        $result = $req->fetchAll();
        $req->closeCursor();
        $qb = $qb->resetQueryParts();
        if ($result and count($result) > 0) {
            $id = $result[0]['id'];
            $qb->update('maps_address_geo')
                ->set('lat', $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR))
                ->set('lng', $qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR))
                ->set('looked_up', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)));
            $req=$qb->execute();
            $qb = $qb->resetQueryParts();
        }
        else {
            $qb->insert('maps_address_geo')
                ->values([
                    'adr'=>$qb->createNamedParameter($adr, IQueryBuilder::PARAM_STR),
                    'adr_norm'=>$qb->createNamedParameter($adr_norm, IQueryBuilder::PARAM_STR),
                    'lat'=>$qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR),
                    'lng'=>$qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR),
                    'looked_up'=>$qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
                ]);
            $req = $qb->execute();
            $id = $qb->getLastInsertId();
            $qb = $qb->resetQueryParts();
        }
    }

    /**
     * get contacts with coordinates
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getContactLetterAvatar($name) {
        $av = $this->avatarManager->getGuestAvatar($name);
        $avatarContent = $av->getFile(64)->getContent();
        return new DataDisplayResponse($avatarContent);
    }
}
