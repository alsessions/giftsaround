<?php

namespace modules\redeem\migrations;

use Craft;
use craft\db\Migration;

/**
 * Install migration for the redeem module
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%redeem_tokens}}');

        return true;
    }

    /**
     * Creates the tables needed for the redeem module
     */
    protected function createTables(): void
    {
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%redeem_tokens}}');
        if ($tableSchema === null) {
            $this->createTable(
                '{{%redeem_tokens}}',
                [
                    'id' => $this->primaryKey(),
                    'token' => $this->string(64)->notNull()->unique(),
                    'userId' => $this->integer()->notNull(),
                    'businessId' => $this->integer()->notNull(),
                    'redeemType' => "ENUM('oneSpecial','monthlySpecial') NOT NULL",
                    'monthIndex' => $this->integer()->null(),
                    'monthData' => $this->text()->null(),
                    'expiresAt' => $this->dateTime()->notNull(),
                    'usedAt' => $this->dateTime()->null(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]
            );
        }
    }

    /**
     * Creates the indexes needed for the redeem module
     */
    protected function createIndexes(): void
    {
        $this->createIndex(null, '{{%redeem_tokens}}', 'token', true);
        $this->createIndex(null, '{{%redeem_tokens}}', ['userId', 'businessId']);
        $this->createIndex(null, '{{%redeem_tokens}}', 'expiresAt');
        $this->createIndex(null, '{{%redeem_tokens}}', 'usedAt');
        $this->createIndex(null, '{{%redeem_tokens}}', ['userId', 'businessId', 'redeemType']);
    }

    /**
     * Adds the foreign keys needed for the redeem module
     */
    protected function addForeignKeys(): void
    {
        $this->addForeignKey(
            null,
            '{{%redeem_tokens}}',
            'userId',
            '{{%users}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%redeem_tokens}}',
            'businessId',
            '{{%elements}}',
            'id',
            'CASCADE'
        );
    }
}
