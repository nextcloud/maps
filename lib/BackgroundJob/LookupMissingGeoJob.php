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

use OCA\Maps\Service\AddressService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

class LookupMissingGeoJob extends QueuedJob {

	/**
	 * LookupMissingGeoJob constructor.
	 *
	 * A QueuedJob to lookup missing geo information of addresses
	 */
	public function __construct(
		ITimeFactory $timeFactory,
		private AddressService $addressService,
		private IJobList $jobList,
	) {
		parent::__construct($timeFactory);
	}

	public function run($argument) {
		\OCP\Server::get(LoggerInterface::class)->debug('Maps address lookup cronjob executed');
		// lookup at most 200 addresses
		if (!$this->addressService->lookupMissingGeo(200)) {
			// if not all addresses where looked up successfully add a new job for next time
			$this->jobList->add(LookupMissingGeoJob::class, []);
		}
	}
}
