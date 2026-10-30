<?php

namespace statikbe\translate\migrations;

use craft\db\Migration;

/**
 * 5.3.0: the overrides table, for sites that installed the plugin before.
 */
class m261030_000000_overrides_table extends Migration
{
    public function safeUp(): bool
    {
        Install::createOverridesTable($this);
        return true;
    }

    public function safeDown(): bool
    {
        echo "m261030_000000_overrides_table cannot be reverted.\n";
        return false;
    }
}
