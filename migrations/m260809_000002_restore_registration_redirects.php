<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

class m260809_000002_restore_registration_redirects extends Migration
{
    public function safeUp(): bool
    {
        $formId = Craft::$app->getDb()->createCommand(
            'SELECT [[id]] FROM {{%freeform_forms}} WHERE [[handle]] = :handle',
            ['handle' => 'userRegistration']
        )->queryScalar();

        if (!$formId) {
            return true;
        }

        $this->updateFormReturnUrl((int)$formId);
        $this->updateStripeFieldRedirects((int)$formId);

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }

    private function updateFormReturnUrl(int $formId): void
    {
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

    private function updateStripeFieldRedirects(int $formId): void
    {
        $fields = Craft::$app->getDb()->createCommand(
            'SELECT [[id]], [[metadata]]
             FROM {{%freeform_forms_fields}}
             WHERE [[formId]] = :formId
               AND [[type]] LIKE :fieldType',
            [
                'formId' => $formId,
                'fieldType' => '%StripeField',
            ]
        )->queryAll();

        foreach ($fields as $field) {
            $data = json_decode((string)$field['metadata']);
            if (!$data) {
                continue;
            }

            $data->redirectSuccess = '/register/complete?paymentIntent={{ paymentIntent.id }}';
            $data->redirectFailed = '/register/payment-failed?paymentIntent={{ paymentIntent.id }}';

            $this->update(
                '{{%freeform_forms_fields}}',
                ['metadata' => json_encode($data)],
                ['id' => $field['id']]
            );
        }
    }
}
