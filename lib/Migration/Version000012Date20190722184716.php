<?php

declare(strict_types=1);

namespace OCA\Maps\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use OCP\IDBConnection;
use Doctrine\DBAL\Types\Type;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000012Date20190722184716 extends SimpleMigrationStep {

	protected $db;

	public function __construct(IDBConnection $connection) {
		$this->db = $connection;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();

		if (!$schema->hasTable('maps_address_geo')) {
			$table = $schema->createTable('maps_address_geo');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 41,
			]);
			$table->addColumn('contact_uid', 'string', [
				'notnull' => true,
				'default' => '',
				'length' => 64,
			]);
			$table->addColumn('adr', 'string', [
				'notnull' => true,
				'default' => '',
				'length' => 255,
			]);
			$table->addColumn('adr_norm', 'string', [
				'notnull' => true,
				'default' => '',
				'length' => 255,
			]);
			$table->addColumn('lat', 'float', [
				'notnull' => false,
				'length' => 10,
			]);
			$table->addColumn('lng', 'float', [
				'notnull' => false,
				'length' => 10,
			]);
			$table->addColumn('looked_up', 'boolean', [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['adr'], 'maps_adr');
			$table->addIndex(['adr_norm'], 'maps_adr_norm');
			$table->addIndex(['contact_uid'], 'maps_contact_uid');
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
