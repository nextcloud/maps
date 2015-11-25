<?php
namespace OCA\Maps\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method string getName()
 * @method void setName(string $value)
 * @method string getTimestamp()
 * @method void setTimestamp(string $value)
 * @method string getLat()
 * @method void setLat(string $value)
 * @method string getLng()
 * @method void setLng(string $value)
 */
class Favorite extends Entity {
	public $userId;
	public $name;
	public $timestamp;
	public $lat;
	public $lng;
}
