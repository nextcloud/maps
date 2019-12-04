<?php


namespace OCA\Maps\DB;

use OC\Share\Constants;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\Security\ISecureRandom;


class FavoriteShareMapper extends QBMapper {
  /* @var ISecureRandom */
  private $secureRandom;

  public function __construct(IDBConnection $db, ISecureRandom $secureRandom) {
    parent::__construct($db, 'maps_favorite_shares');

    $this->secureRandom = $secureRandom;
  }

  /**
   * @param $token
   * @return Entity|null
   * @throws DoesNotExistException
   * @throws MultipleObjectsReturnedException
   */
  public function findByToken($token) {
    $qb = $this->db->getQueryBuilder();

    $qb->select("*")
      ->from($this->getTableName())
      ->where(
        $qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
      );

    return $this->findEntity($qb);
  }

  /**
   * @param $owner
   * @param $category
   * @return Entity
   */
  public function create($owner, $category) {
    $token = $this->secureRandom->generate(
      Constants::TOKEN_LENGTH,
      ISecureRandom::CHAR_HUMAN_READABLE
    );

    $newShare = new FavoriteShare();
    $newShare->setToken($token);
    $newShare->setCategory($category);
    $newShare->setOwner($owner);

    return $this->insert($newShare);
  }

  /**
   * @param $owner
   * @return array|Entity[]
   */
  public function findAllByOwner($owner) {
    $qb = $this->db->getQueryBuilder();

    $qb->select("*")
      ->from($this->getTableName())
      ->where(
        $qb->expr()->eq('owner', $qb->createNamedParameter($owner, IQueryBuilder::PARAM_STR))
      );

    return $this->findEntities($qb);
  }

  /**
   * @param $owner
   * @param $category
   * @return Entity
   * @throws DoesNotExistException
   * @throws MultipleObjectsReturnedException
   */
  public function findByOwnerAndCategory($owner, $category) {
    $qb = $this->db->getQueryBuilder();

    $qb->select("*")
      ->from($this->getTableName())
      ->where(
        $qb->expr()->eq('category', $qb->createNamedParameter($category, IQueryBuilder::PARAM_STR))
      )->andWhere(
        $qb->expr()->eq('owner', $qb->createNamedParameter($owner, IQueryBuilder::PARAM_INT))
      );

    return $this->findEntity($qb);
  }

  /**
   * @param $owner
   * @param $category
   * @return Entity|null
   */
  public function findOrCreateByOwnerAndCategory($owner, $category) {
    /* @var Entity */
    $entity = null;

    try {
      $entity = $this->findByOwnerAndCategory($owner, $category);
    } catch (DoesNotExistException $e) {
      $entity = $this->create($owner, $category);
    } catch (MultipleObjectsReturnedException $e) {}

    return $entity;
  }

  /**
   * @param $owner
   * @param $category
   * @return bool
   */
  public function removeByOwnerAndCategory($owner, $category) {
    try {
      $entity = $this->findByOwnerAndCategory($owner, $category);
    } catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
      return false;
    }

    $this->delete($entity);

    return true;
  }
}
