<?php
namespace OCA\Maps\Db;

use OCP\AppFramework\Db\Entity;

class Device extends Entity {
	public $userId;
	public $name;
	public $hash;
	public $created;
}