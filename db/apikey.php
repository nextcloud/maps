<?php
namespace OCA\Maps\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method string getApiKey()
 * @method void setApiKey(string $value)
 */
class ApiKey extends Entity {
	public $userId;
    public $apiKey;
}
