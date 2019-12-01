<?php


namespace OCA\Maps\DB;

use OCP\AppFramework\Db\Entity;

class FavoriteShare extends Entity {
  public $owner;
  public $token;
  public $category;
  public $allowEdits = false; // TODO

  public function __construct() {
    $this->addType('owner', 'string');
    $this->addType('token', 'string');
    $this->addType('category', 'string');
    $this->addType('allowEdits', 'boolean');
  }
}
