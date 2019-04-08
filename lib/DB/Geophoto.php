<?php

/**
 * Nextcloud - maps
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Piotr Bator <prbator@gmail.com>
 * @copyright Piotr Bator 2017
 */

namespace OCA\Maps\DB;

use OCP\AppFramework\Db\Entity;

class Geophoto extends Entity {

    protected $fileId;
    protected $lat;
    protected $lng;
    protected $dateTaken;
    protected $userId;

    public function __construct() {
        $this->addType('fileId', 'integer');
        $this->addType('lat', 'float');
        $this->addType('lng', 'float');
        $this->addType('dateTaken', 'integer');
    }
}
