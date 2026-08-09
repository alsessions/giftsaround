<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

class m260809_000001_payment_gated_registration extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%paid_registration_payments}}')) {
            $this->createTable('{{%paid_registration_payments}}', [
                'id' => $this->primaryKey(),
                'paymentIntent' => $this->string(64)->notNull(),
                'userId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%paid_registration_payments}}', 'paymentIntent', true);
            $this->createIndex(null, '{{%paid_registration_payments}}', 'userId');
            $this->addForeignKey(null, '{{%paid_registration_payments}}', 'userId', '{{%users}}', 'id', 'CASCADE');
        }

        $formId = $this->userRegistrationFormId();
        $stripeIntegrationId = $this->stripeIntegrationId();

        if ($formId && $stripeIntegrationId) {
            $this->update(
                '{{%freeform_forms_integrations}}',
                ['enabled' => false],
                [
                    'and',
                    ['formId' => $formId],
                    ['not', ['integrationId' => $stripeIntegrationId]],
                ]
            );
        }

        $this->updateFormReturnUrl();

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%paid_registration_payments}}');

        return true;
    }

    private function userRegistrationFormId(): ?int
    {
        return Craft::$app->getDb()->createCommand(
            'SELECT [[id]] FROM {{%freeform_forms}} WHERE [[handle]] = :handle',
            ['handle' => 'userRegistration']
        )->queryScalar() ?: null;
    }

    private function stripeIntegrationId(): ?int
    {
        return Craft::$app->getDb()->createCommand(
            'SELECT [[id]] FROM {{%freeform_integrations}} WHERE [[handle]] = :handle',
            ['handle' => 'stripe']
        )->queryScalar() ?: null;
    }

    private function updateFormReturnUrl(): void
    {
        $formId = $this->userRegistrationFormId();

        if (!$formId) {
            return;
        }

        $metadata = Craft::$app->getDb()->createCommand(
            'SELECT [[metadata]] FROM {{%freeform_forms}} WHERE [[id]] = :id',
            ['id' => $formId]
        )->queryScalar();

        $data = json_decode((string)$metadata);
        if (!$data || !isset($data->behavior)) {
            return;
        }

        $data->behavior->returnUrl = '/register/complete?submissionToken={{ submission.token }}';

        $this->update('{{%freeform_forms}}', ['metadata' => json_encode($data)], ['id' => $formId]);
    }
}
