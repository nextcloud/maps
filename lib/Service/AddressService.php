<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Arne Hamann
 * @copyright Arne Hamann 2019
 */

namespace OCA\Maps\Service;

use \OCA\Maps\BackgroundJob\LookupMissingGeoJob;
use \OCP\ILogger;
use \OCP\ICache;
use \OCP\BackgroundJob\IJobList;
use \OCP\DB\QueryBuilder\IQueryBuilder;
use \Sabre\VObject\Reader;
use \OCP\Files\IAppData;
use \OCP\Files\SimpleFS\ISimpleFile;
use \OCP\Files\NotFoundException;

class AddressService {
    private $qb;
    private $dbconnection;
    private $logger;
    private $jobList;
    private $appData;

    public function __construct(ICache $cache, ILogger $logger, IJobList $jobList, IAppData $appData) {
        $this->qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();
        $this->dbconnection = \OC::$server->getDatabaseConnection();
        $this->cache = $cache;
        $this->logger = $logger;
        $this->jobList = $jobList;
        $this->appData = $appData;
    }

    //converts the address to geo lat;lon
    public function addressToGeo($adr) {
        $geo = $this->lookupAddress($adr);
        return strval($geo[0]).";".strval($geo[1]);
    }

    /* Safely looks up an adr string
     * First: Checks if the adress is knwon and in the db
     *      Uses the this geo if it was looked up externally
     *      Look's it up if it was not looked up
     * @param $adr
     * @return array($lat,$lng,$lookedUp)
     */
    public function lookupAddress($adr){
        $adr_norm = strtolower(preg_replace('/\s+/', '', $adr));
        $this->qb->select('id','lat','lng','looked_up')
            ->from('maps_address_geo')
            ->where($this->qb->expr()->eq('adr_norm', $this->qb->createNamedParameter($adr_norm, IQueryBuilder::PARAM_STR)));
        $req=$this->qb->execute();
        $lat = null;
        $lng = null;
        $inDb = False;
        while ($row = $req->fetch()) {
            if ($row['looked_up']){
                $id = $row['id'];
                $lat = $row['lat'];
                $lng = $row['lng'];
                $lookedUp = False;
                $inDb = True;
            } else {
                $id = $row['id'];
                $geo = $this->unsafeLookupAddress($adr);
                $lat = $geo[0];
                $lng = $geo[1];
                $lookedUp = $geo[2];
                $inDb = True;
            }
            break;
        }
        $req->closeCursor();
        $qb = $this->qb->resetQueryParts();
        if (!$inDb) {
            $foo = $this->scheduleForLookup($adr);
            $id = $foo[0];
            $lat = $foo[1];
            $lng = $foo[2];
            $lookedUp = $foo[3];

        } else {
            if ($lookedUp) {
                $this->qb->update('maps_address_geo')
                    ->set("lat", $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR))
                    ->set("lng", $qb->createNamedParameter($lng, IQueryBuilder::PARAM_STR))
                    ->set("looked_up", $qb->createNamedParameter($lookedUp, IQueryBuilder::PARAM_BOOL))
                    ->where($this->qb->expr()->eq('id', $this->qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)));
                $req=$this->qb->execute();
                $qb = $this->qb->resetQueryParts();
            }
        }

        return [$lat, $lng, $lookedUp];
    }

    private function getLastLookupFile():ISimpleFile {
        try{
            $folder = $this->appData->getFolder("cache");
        } catch(NotFoundException $e) {
           $folder = $this->appData->newFolder("cache");
        }
        if($folder->fileExists('maps_address_last_lookup')) {
            $file = $folder->getFile('maps_address_last_lookup');
        } else {
            $file = $folder->newFile('maps_address_last_lookup');
        }
        return $file;
    }

    private function getLastLookup():int{
        $file = $this->getLastLookupFile();
        return (int) $file->getContent();
    }

    private function setLastLookup(){
        $file = $this->getLastLookupFile();
        $file->putContent(time());
    }

    //looks up the address on external provider returns lat, lon, lookupstate
    private function unsafeLookupAddress($adr){
        if (time() - $this->getLastLookup() >= 1) {
            $opts = array('http' =>
                array(
                    'method'  => 'GET',
                    'user_agent' => "Nextcloud Maps app",
                )
            );
            $context  = stream_context_create($opts);
            $result_json = @file_get_contents(
                "https://nominatim.openstreetmap.org/search.php?q="
                .urlencode($adr)
                ."&format=json", False, $context
            );
            if ($result_json !== False) {
                $result=\json_decode(
                    $result_json
                    ,true);
                if(!(key_exists("request_failed",$result) AND $result["request_failed"])) {
                    $this->logger->debug("External looked up address: " . $adr . " with result" . print_r($result, true));
                    $this->setLastLookup();
                    if (sizeof($result) > 0) {
                        if (key_exists("lat", $result[0]) AND
                            key_exists("lon", $result[0])
                        ) {
                            return [
                                $result[0]["lat"],
                                $result[0]["lon"], true
                            ];
                        }
                    }
                    return [null, null, true];
                }
            }
            $this->logger->debug("Externally looked failed");
        }
        return [null, null, False];
    }

    public function  scheduleVCardForLookup($cardData){
        $vCard = Reader::read($cardData);
        foreach ($vCard->children() as $property) {
            if ($property->name === 'ADR') {
                $adr = $property->getValue();
                if ($adr !== ';;;;;;') {
                    $this->lookupAddress($property->getValue());
                }
            }
        }
    }

    //Schedules the address for an external lookup
    private function scheduleForLookup($adr) {
        $geo = $this->unsafeLookupAddress($adr);
        $adr_norm = strtolower(preg_replace('/\s+/', '', $adr));
        $this->qb->insert('maps_address_geo')
            ->values([
                'adr'=>$this->qb->createNamedParameter($adr,IQueryBuilder::PARAM_STR),
                'adr_norm'=>$this->qb->createNamedParameter($adr_norm,IQueryBuilder::PARAM_STR),
                'lat'=>$this->qb->createNamedParameter($geo[0],IQueryBuilder::PARAM_STR),
                'lng'=>$this->qb->createNamedParameter($geo[1],IQueryBuilder::PARAM_STR),
                'looked_up'=>$this->qb->createNamedParameter($geo[2],IQueryBuilder::PARAM_BOOL),
            ]);
        $req = $this->qb->execute();
        $id = $this->qb->getLastInsertId();
        $qb = $this->qb->resetQueryParts();
        if (!$geo[2]) {
            $this->jobList->add(LookupMissingGeoJob::class,[]);
        }
        return [$id, $geo[0], $geo[1], $geo[2]];
    }

    //lookus up the geo information which have not been looked up
    public function lookupMissingGeo($max=200):bool {
        //Stores if all addresses where looked up
        $lookedupall=True;
        $this->qb->select("adr")
            ->from('maps_address_geo')
            ->where($this->qb->expr()->eq('looked_up', $this->qb->createNamedParameter(False, IQueryBuilder::PARAM_BOOL)))
            ->setMaxResults($max);
        $req=$this->qb->execute();
        $result = $req->fetchAll();
        $req->closeCursor();
        $i=0;
        foreach ($result as $row) {
            $i++;
            $geo = $this->lookupAddress($row['adr']);
            //Lookup failed
            if(!$geo[2]){
                $lookedupall = False;
            }
            \sleep(1);
            \usleep(\rand(100,100000));
        }
        //not all addresses where loaded from database
        if($i===$max){
            $lookedupall = False;
        }
        if ($lookedupall){
            $this->logger->debug("Successfully looked up all addresses during cron job");
        } else {
            $this->logger->debug("Failed to look up all addresses during cron job");
        }
        return $lookedupall;
    }
}