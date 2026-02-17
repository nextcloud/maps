<?php

declare(strict_types=1);

namespace OCA\Maps\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version100100Date20230731135102 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $db,
	) {
	}

	/**
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('maps_device_shares')) {
			$schema->dropTable('maps_device_shares');
		}

		if (!$schema->hasTable('maps_device_shares')) {
			$table = $schema->createTable('maps_device_shares');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 41,
			]);
			$table->addColumn('device_id', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('timestamp_from', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('timestamp_to', 'bigint', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('token', 'string', [
				'notnull' => true,
				'length' => 64,
			]);

			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}

	/**
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
