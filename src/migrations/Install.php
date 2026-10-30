<?php

namespace statikbe\translate\migrations;

use craft\db\Migration;
use statikbe\translate\services\Overrides;

/**
 * Creates the overrides table (used when `storeInDatabase` is on).
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        self::createOverridesTable($this);
        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(Overrides::TABLE);
        return true;
    }

    /**
     * Creates the table when it doesn't exist yet (shared with the upgrade migration).
     */
    public static function createOverridesTable(Migration $migration): void
    {
        if ($migration->db->tableExists(Overrides::TABLE)) {
            return;
        }

        $migration->createTable(Overrides::TABLE, [
            'id' => $migration->primaryKey(),
            'language' => $migration->string(12)->notNull(),
            'category' => $migration->string(255)->notNull(),
            'message' => $migration->text()->notNull(),
            'messageHash' => $migration->char(40)->notNull(),
            'translation' => $migration->text()->notNull(),
            'userId' => $migration->integer(),
            'dateCreated' => $migration->dateTime()->notNull(),
            'dateUpdated' => $migration->dateTime()->notNull(),
            'uid' => $migration->uid(),
        ]);
        $migration->createIndex(null, Overrides::TABLE, ['language', 'category', 'messageHash'], true);
        $migration->createIndex(null, Overrides::TABLE, ['category']);
        $migration->addForeignKey(null, Overrides::TABLE, ['userId'], '{{%users}}', ['id'], 'SET NULL');
    }
}
