<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

/**
 * m250925_203610_install_redeem_tokens migration.
 */
class m250925_203610_install_redeem_tokens extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTable('{{%redeem_tokens}}', [
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
        ]);

        // Create indexes
        $this->createIndex(null, '{{%redeem_tokens}}', 'token', true);
        $this->createIndex(null, '{{%redeem_tokens}}', ['userId', 'businessId']);
        $this->createIndex(null, '{{%redeem_tokens}}', 'expiresAt');
        $this->createIndex(null, '{{%redeem_tokens}}', 'usedAt');
        $this->createIndex(null, '{{%redeem_tokens}}', ['userId', 'businessId', 'redeemType']);

        // Add foreign keys
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
}
