<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH
 * SPDX-FileContributor: Carl Schwan
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Maps\AppFramework\Db;

use Generator;
use OCA\Maps\AppFramework\Db\Attribute\Column;
use OCA\Maps\AppFramework\Db\Attribute\Entity;
use OCA\Maps\AppFramework\Db\Attribute\Id;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Server;
use OCP\Snowflake\ISnowflakeGenerator;

/**
 * @template T as object
 * @since 35.0.0
 */
class Repository {
	private string $tableName;

	/** @var array<string, string> */
	private array $_mappingColumnToTypes = [];

	/** @var array<string, string> */
	private array $_mappingColumnToProperty = [];

	/** @var array<string, string> */
	private array $_mappingPropertyToColumn = [];

	/** @var \ReflectionClass<T> */
	private \ReflectionClass $reflection;

	private string $idProperty;

	/**
	 * @param IDBConnection $connection
	 * @param class-string<T> $entityClass
	 * @throws \ReflectionException
	 */
	public function __construct(
		protected readonly IDBConnection $connection,
		protected readonly string $entityClass,
	) {
		$this->reflection = new \ReflectionClass($this->entityClass);

		$entities = $this->reflection->getAttributes(Entity::class, \ReflectionAttribute::IS_INSTANCEOF);
		if (count($entities) !== 1) {
			throw new \InvalidArgumentException('The given entity is missing or has too many of the required #[Entity] attribute');
		}

		$this->tableName = $entities[0]->newInstance()->name;

		foreach ($this->reflection->getProperties() as $property) {
			$columnAttributes = $property->getAttributes(Column::class, \ReflectionAttribute::IS_INSTANCEOF);
			if (count($columnAttributes) === 0) {
				continue;
			}

			/** @var Column $columnAttribute */
			$columnAttribute = $columnAttributes[0]->newInstance();
			$this->_mappingColumnToTypes[$columnAttribute->name] = $columnAttribute->type;
			$this->_mappingColumnToProperty[$columnAttribute->name] = $property->getName();
			$this->_mappingPropertyToColumn[$property->getName()] = $columnAttribute->name;

			/** @var list<\ReflectionAttribute<Id>> $ids */
			$ids = $property->getAttributes(Id::class, \ReflectionAttribute::IS_INSTANCEOF);
			if (!empty($ids)) {
				$this->idProperty = $property->getName();
			}
		}
	}

	/**
	 * Runs a sql query and yields each resulting entity to obtain database entries in a memory-efficient way
	 *
	 * @param IQueryBuilder $query
	 * @return Generator Generator of fetched entities
	 * @psalm-return Generator<T> Generator of fetched entities
	 * @throws Exception
	 */
	public function yieldEntities(IQueryBuilder $query): Generator {
		$result = $query->executeQuery();
		try {
			while ($row = $result->fetch()) {
				yield $this->mapRowToEntity($row);
			}
		} finally {
			$result->closeCursor();
		}
	}

	/**
	 * Runs a sql query and returns an array of entities
	 *
	 * @param IQueryBuilder $query
	 * @psalm-return list<T> all fetched entities
	 * @throws Exception
	 */
	public function findEntities(IQueryBuilder $query): array {
		return iterator_to_array($this->yieldEntities($query));
	}

	private function buildDebugMessage(string $msg, IQueryBuilder $sql): string {
		return $msg . ': query "' . $sql->getSQL() . '"; ';
	}

	/**
	 * @param array<string, mixed> $row
	 * @return T
	 */
	private function mapRowToEntity(mixed $row): object {
		$entity = new $this->entityClass();
		foreach ($row as $column => $value) {
			$property = $this->_mappingColumnToProperty[$column];
			$type = $this->_mappingColumnToTypes[$column];
			if ($type === Types::BLOB) {
				// (B)LOB is treated as string when we read from the DB
				if (is_resource($value)) {
					$value = stream_get_contents($value);
				}
				$type = Types::STRING;
			}

			if ($column === $this->idProperty) {
				$entity->$property = (string)$value;
				continue;
			}

			switch ($type) {
				case Types::BIGINT:
				case Types::SMALLINT:
					settype($value, Types::INTEGER);
					break;
				case Types::BINARY:
				case Types::DECIMAL:
				case Types::TEXT:
					settype($value, Types::STRING);
					break;
				case Types::TIME:
				case Types::DATE:
				case Types::DATETIME:
				case Types::DATETIME_TZ:
					if (!$value instanceof \DateTime) {
						$value = new \DateTime($value);
					}
					break;
				case Types::TIME_IMMUTABLE:
				case Types::DATE_IMMUTABLE:
				case Types::DATETIME_IMMUTABLE:
				case Types::DATETIME_TZ_IMMUTABLE:
					if (!$value instanceof \DateTimeImmutable) {
						$value = new \DateTimeImmutable($value);
					}
					break;
				case Types::JSON:
					if (!is_array($value)) {
						$value = json_decode($value, true);
					}
					break;
			}
			$entity->$property = $value;
		}
		return $entity;
	}

	/**
	 * Insert the entity in the database.
	 *
	 * This will additionally generate a value for the primary key.
	 *
	 * @psalm-param T $entity
	 * @return T
	 */
	public function insert(object $entity): object {
		$insert = $this->connection->getQueryBuilder();

		$isSnowflake = false;
		$primaryProperty = null;
		$values = [];
		foreach ($this->reflection->getProperties() as $property) {
			/** @var list<\ReflectionAttribute<Column>> $columns */
			$columns = $property->getAttributes(Column::class, \ReflectionAttribute::IS_INSTANCEOF);
			if (empty($columns)) {
				continue; // Not in the DB
			}

			$column = $columns[0]->newInstance();

			/** @var list<\ReflectionAttribute<Id>> $ids */
			$ids = $property->getAttributes(Id::class, \ReflectionAttribute::IS_INSTANCEOF);
			if (count($ids) > 0 && $property->getValue($entity) === null) {
				$primaryProperty = $property;
				$generatorClass = $ids[0]->newInstance()->generatorClass;
				$generator = Server::get($generatorClass);
				/** @psalm-suppress UndefinedClass NC 33 and above */
				if (class_exists(ISnowflakeGenerator::class) && $generator instanceof ISnowflakeGenerator) {
					$isSnowflake = true;
					/** @psalm-suppress UndefinedClass */
					$values[$column->name] = $generator->nextId();
					$property->setValue($entity, $insert->createNamedParameter($values[$column->name]));
				}
			} else {
				$type = $this->getParameterType($column->type, false);
				$values[$column->name] = $insert->createNamedParameter($property->getValue($entity), $type);
			}
		}

		$insert->insert($this->tableName)
			->values($values)
			->executeStatement();

		if (!$isSnowflake) {
			$primaryProperty->setValue($entity, $insert->getLastInsertId());
		}
		return $entity;
	}

	/**
	 * @psalm-param T $entity
	 * @return T
	 */
	public function update(object $entity): object {
		$update = $this->connection->getQueryBuilder();
		$update->update($this->tableName);

		foreach ($this->reflection->getProperties() as $property) {
			/** @var list<\ReflectionAttribute<Column>> $columns */
			$columns = $property->getAttributes(Column::class, \ReflectionAttribute::IS_INSTANCEOF);
			if (empty($columns)) {
				continue; // Not in the DB
			}

			$column = $columns[0]->newInstance();

			if (count($property->getAttributes(Id::class, \ReflectionAttribute::IS_INSTANCEOF)) !== 0) {
				if ($property->getValue($entity) === null) {
					throw new \LogicException('Trying to update an entity with no primary key set.');
				}

				$update->andWhere($update->expr()->eq($this->_mappingPropertyToColumn[$this->idProperty], $update->createNamedParameter($property->getValue($entity))));
				// don't update the id
				continue;
			};

			$type = $this->getParameterType($column->type, false);
			$update->set($column->name, $update->createNamedParameter($property->getValue($entity), $type));
		}

		$update->executeStatement();
		return $entity;
	}

	public function delete(object $entity): void {
		$delete = $this->connection->getQueryBuilder();
		$delete->delete($this->tableName);

		$foundId = false;
		foreach ($this->reflection->getProperties() as $property) {
			$columns = $property->getAttributes(Column::class, \ReflectionAttribute::IS_INSTANCEOF);
			if (empty($columns)) {
				continue; // Not in the DB
			}

			$column = $columns[0]->newInstance();

			if (count($property->getAttributes(Id::class, \ReflectionAttribute::IS_INSTANCEOF)) !== 0) {
				$delete->andWhere($delete->expr()->eq($column->name, $property->getValue($entity)));
				$foundId = true;
			};
		}

		if (!$foundId) {
			throw new \LogicException('The given entity is missing a required #[Id] attribute on one of its properties.');
		}

		$delete->executeStatement();
	}

	/**
	 * @param Types::* $type
	 * @return IQueryBuilder::PARAM_*
	 */
	private function getParameterType(string $type, bool $isArray): string|int {
		if ($isArray) {
			return match ($type) {
				Types::INTEGER, Types::SMALLINT => IQueryBuilder::PARAM_INT_ARRAY,
				Types::STRING => IQueryBuilder::PARAM_STR_ARRAY,
				Types::JSON => IQueryBuilder::PARAM_JSON,
				default => throw new \LogicException("Parameter type '$type' is not supported as an array."),
			};
		}

		return match ($type) {
			Types::INTEGER, Types::SMALLINT => IQueryBuilder::PARAM_INT,
			Types::STRING => IQueryBuilder::PARAM_STR,
			Types::BOOLEAN => IQueryBuilder::PARAM_BOOL,
			Types::BLOB => IQueryBuilder::PARAM_LOB,
			Types::DATE, Types::DATETIME => IQueryBuilder::PARAM_DATETIME_MUTABLE,
			Types::DATETIME_TZ => IQueryBuilder::PARAM_DATETIME_TZ_MUTABLE,
			Types::DATE_IMMUTABLE => IQueryBuilder::PARAM_DATE_IMMUTABLE,
			Types::DATETIME_IMMUTABLE => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
			Types::DATETIME_TZ_IMMUTABLE => IQueryBuilder::PARAM_DATETIME_TZ_IMMUTABLE,
			Types::TIME => IQueryBuilder::PARAM_TIME_MUTABLE,
			Types::TIME_IMMUTABLE => IQueryBuilder::PARAM_TIME_IMMUTABLE,
			Types::JSON => IQueryBuilder::PARAM_JSON,
			default => IQueryBuilder::PARAM_STR,
		};
	}

	/**
	 * Finds entities by a set of criteria.
	 *
	 * Use the property names for the criteria and orderBy key.
	 *
	 * @param array<string, int|float|string|list<int|float|string>> $criteria
	 * @param array<string, 'asc'|'desc'>|null $orderBy
	 * @return \Generator<T>
	 * @since 33.0.0
	 */
	public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): \Generator {
		$qb = $this->getSelectQueryBuilder($criteria, $orderBy);

		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}

		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}

		return $this->yieldEntities($qb);
	}

	/**
	 * @param array<string, int|float|string|list<int|float|string>> $criteria
	 * @return int The number of rows deleted
	 * @throws Exception
	 */
	public function deleteBy(array $criteria, ?int $limit = null): int {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete($this->tableName);

		foreach ($criteria as $property => $value) {
			$column = $this->_mappingPropertyToColumn[$property];
			$type = $this->getParameterType($this->_mappingColumnToTypes[$column], is_array($value));
			$type = $this->getParameterType($this->_mappingColumnToTypes[$column], is_array($value));
			if (is_array($value)) {
				// IN expression
				$qb->andWhere($qb->expr()->in($column, $qb->createNamedParameter($value, $type)));
			} else {
				// = expression
				$qb->andWhere($qb->expr()->eq($column, $qb->createNamedParameter($value, $type)));
			}
		}

		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}

		return $qb->executeStatement();
	}

	/**
	 * Finds a single entity by a set of criteria.
	 *
	 * @param array<string, int|float|string|list<int|float|string>> $criteria
	 * @param array<string, 'asc'|'desc'>|null $orderBy
	 * @return T
	 * @throws DoesNotExistException
	 */
	public function findOneBy(array $criteria, array $orderBy = []): object {
		$qb = $this->getSelectQueryBuilder($criteria, $orderBy);

		$qb->setMaxResults(1);

		return $this->findEntity($qb);
	}

	/**
	 * @param array<string, int|float|string|list<int|float|string>> $criteria
	 * @param array<string, 'asc'|'desc'>|null $orderBy
	 */
	private function getSelectQueryBuilder(array $criteria, array $orderBy = []): IQueryBuilder {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName);

		foreach ($criteria as $property => $value) {
			$column = $this->_mappingPropertyToColumn[$property];
			$type = $this->getParameterType($this->_mappingColumnToTypes[$column], is_array($value));
			if (is_array($value)) {
				// IN expression
				$qb->andWhere($qb->expr()->in($column, $qb->createNamedParameter($value, $type)));
			} else {
				// = expression
				$qb->andWhere($qb->expr()->eq($column, $qb->createNamedParameter($value, $type)));
			}
		}
		foreach ($orderBy as $field => $direction) {
			$qb->addOrderBy($qb->createNamedParameter($field), $direction);
		}

		return $qb;
	}

	/**
	 * Returns a db result and throws exceptions when there are more or less
	 * results
	 *
	 * @param IQueryBuilder $query
	 * @psalm-return T the entity
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException if more than one item exist
	 * @throws DoesNotExistException if the item does not exist
	 * @since 33.0.0
	 */
	protected function findEntity(IQueryBuilder $query): object {
		$result = $query->executeQuery();

		$row = $result->fetch();
		if ($row === false) {
			$result->closeCursor();
			$msg = $this->buildDebugMessage(
				'Did expect one result but found none when executing', $query
			);
			throw new DoesNotExistException($msg);
		}

		$row2 = $result->fetch();
		$result->closeCursor();
		if ($row2 !== false) {
			$msg = $this->buildDebugMessage(
				'Did not expect more than one result when executing', $query
			);
			throw new MultipleObjectsReturnedException($msg);
		}

		return $this->mapRowToEntity($row);
	}

	public function getTableName(): string {
		return $this->tableName;
	}
}
