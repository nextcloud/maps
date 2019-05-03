<?php

declare(strict_types=1);

namespace OCA\Maps\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000008Date20190428142257 extends SimpleMigrationStep {

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
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('maps_favorites')) {
			$table = $schema->createTable('maps_favorites');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 41,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('date_modified', 'bigint', [
				'notnull' => true,
				'length' => 15,
				'default' => 0,
			]);
			$table->addColumn('date_created', 'bigint', [
				'notnull' => true,
				'length' => 15,
				'default' => 0,
			]);
			$table->addColumn('lat', 'float', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('lng', 'float', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('category', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('comment', 'string', [
				'notnull' => false,
				'length' => 500,
			]);
			$table->addColumn('extensions', 'string', [
				'notnull' => false,
				'length' => 500,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('maps_apikeys')) {
			$table = $schema->createTable('maps_apikeys');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 41,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('api_key', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('maps_photos')) {
			$table = $schema->createTable('maps_photos');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 41,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('file_id', 'bigint', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('lat', 'float', [
				'notnull' => false,
			]);
			$table->addColumn('lng', 'float', [
				'notnull' => false,
			]);
			$table->addColumn('date_taken', 'bigint', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['date_taken'], 'maps_date_taken');
		}

		if (!$schema->hasTable('maps_tracks')) {
			$table = $schema->createTable('maps_tracks');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 41,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('file_id', 'bigint', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('etag', 'string', [
				'notnull' => true,
				'length' => 100,
				'default' => '',
			]);
			$table->addColumn('color', 'string', [
				'notnull' => false,
				'length' => 7,
			]);
			$table->addColumn('metadata', 'string', [
				'notnull' => false,
				'length' => 500,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('maps_devices')) {
			$table = $schema->createTable('maps_devices');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 41,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('user_agent', 'string', [
				'notnull' => true,
				'length' => 200,
			]);
			$table->addColumn('color', 'string', [
				'notnull' => false,
				'length' => 7,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('maps_device_points')) {
			$table = $schema->createTable('maps_device_points');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 41,
			]);
			$table->addColumn('device_id', 'bigint', [
				'notnull' => true,
				'length' => 41,
			]);
			$table->addColumn('lat', 'float', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('lng', 'float', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('timestamp', 'bigint', [
				'notnull' => true,
				'length' => 15,
				'default' => 0,
			]);
			$table->addColumn('altitude', 'float', [
				'notnull' => false,
				'length' => 10,
			]);
			$table->addColumn('accuracy', 'float', [
				'notnull' => false,
				'length' => 10,
			]);
			$table->addColumn('battery', 'float', [
				'notnull' => false,
				'length' => 10,
			]);
			$table->setPrimaryKey(['id']);
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
