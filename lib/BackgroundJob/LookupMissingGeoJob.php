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

namespace OCA\Maps\BackgroundJob;

use \OCA\Maps\Service\AddressService;
use \OCP\BackgroundJob\QueuedJob;
use \OCP\BackgroundJob\IJobList;
use \OCP\AppFramework\Utility\ITimeFactory;

class LookupMissingGeoJob extends QueuedJob {

    /** @var AddressService */
    private $addressService;

    /** @var AddressService */
    private $jobList;

    /**
     * LookupMissingGeoJob constructor.
     *
     * A QueuedJob to lookup missing geo information of addresses
     *
     * @param AddressService $service
     * @param IJobList $jobList
     */
    public function __construct(ITimeFactory $timeFactory, AddressService $service, IJobList $jobList) {
        parent::__construct($timeFactory);
        $this->addressService = $service;
        $this->jobList = $jobList;
    }

    public function run($arguments) {
        \OC::$server->getLogger()->debug('Maps address lookup cronjob executed');
        // lookup at most 200 addresses
        if (!$this->addressService->lookupMissingGeo(200)){
            // if not all addresses where looked up successfully add a new job for next time
            $this->jobList->add(LookupMissingGeoJob::class, []);
        }
    }
}
