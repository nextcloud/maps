<?php
namespace OCA\Maps\Db;

use OCP\AppFramework\Db\Entity;

class Location extends Entity {
	public $deviceHash;
	public $lat;
	public $lng;
	public $timestamp;
	public $hdop;
	public $altitude;
	public $speed;
}