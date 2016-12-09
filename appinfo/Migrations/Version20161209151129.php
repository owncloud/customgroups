<?php

namespace OCA\CustomGroups\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20161209151129 extends AbstractMigration {
	/**
	 * @param Schema $schema
	 */
	public function up(Schema $schema) {
		$this->createGroupsTable($schema);
		$this->createMembersTable($schema);
	}

	private function createGroupsTable(Schema $schema) {
		$prefix = $this->connection->getPrefix();
		$table = $schema->createTable("${prefix}custom_group");
		$table->addColumn('group_id', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'length' => 4,
		]);
		$table->addColumn('uri', 'string', [
			'length' => 255,
			'notnull' => true,
		]);
		$table->addColumn('display_name', 'string', [
			'length' => 64,
			'notnull' => true,
		]);
		// TODO: find how to set sort to ascending
		$table->addUniqueIndex(['uri'], 'cg_uri_index');
		$table->setPrimaryKey(['group_id']);
	}

	private function createMembersTable(Schema $schema) {
		$prefix = $this->connection->getPrefix();
		$table = $schema->createTable("${prefix}custom_group_member");
		$table->addColumn('group_id', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'length' => 4,
		]);
		$table->addColumn('user_id', 'string', [
			'length' => 64,
			'notnull' => true,
		]);
		$table->addColumn('is_admin', 'integer', [
			'length' => 4,
			'notnull' => true,
			'default' => 0,
		]);
		$table->setPrimaryKey(['group_id', 'user_id']);
	}

	/**
	 * @param Schema $schema
	 */
	public function down(Schema $schema) {
		$schema->dropTable('custom_group_member');
		$schema->dropTable('custom_group');
	}
}
