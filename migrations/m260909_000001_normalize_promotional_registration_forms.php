<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\db\Query;
use Solspace\Freeform\Elements\Submission;
use yii\db\Expression;

class m260909_000001_normalize_promotional_registration_forms extends Migration
{
    private const FORM_HANDLES = ['mohawkvalley', 'q518'];

    public function safeUp(): bool
    {
        foreach (self::FORM_HANDLES as $handle) {
            $formId = (int)(new Query())
                ->select(['id'])
                ->from('{{%freeform_forms}}')
                ->where(['handle' => $handle])
                ->scalar();

            if (!$formId) {
                continue;
            }

            $this->disableUserIntegrations($formId);
            $this->updateFormRedirect($formId);
            $this->updateStripeRedirects($formId);
            $this->removeCredentialFields($formId, $handle);
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260909_000001_normalize_promotional_registration_forms cannot be reverted safely.\n";

        return false;
    }

    private function disableUserIntegrations(int $formId): void
    {
        $userIntegrationIds = (new Query())
            ->select(['id'])
            ->from('{{%freeform_integrations}}')
            ->where(['class' => 'Solspace\\Freeform\\Integrations\\Elements\\User\\User'])
            ->column();

        if ($userIntegrationIds) {
            $this->update(
                '{{%freeform_forms_integrations}}',
                ['enabled' => false],
                ['formId' => $formId, 'integrationId' => $userIntegrationIds]
            );
        }
    }

    private function updateFormRedirect(int $formId): void
    {
        $metadata = (new Query())
            ->select(['metadata'])
            ->from('{{%freeform_forms}}')
            ->where(['id' => $formId])
            ->scalar();

        $data = json_decode((string)$metadata);
        if (!$data || !isset($data->behavior)) {
            return;
        }

        $data->behavior->returnUrl = '/register/complete?submissionToken={{ submission.token }}';
        $data->behavior->successBehavior = 'redirect-return-url';

        $this->update('{{%freeform_forms}}', ['metadata' => json_encode($data)], ['id' => $formId]);
    }

    private function updateStripeRedirects(int $formId): void
    {
        $fields = (new Query())
            ->select(['id', 'metadata'])
            ->from('{{%freeform_forms_fields}}')
            ->where(['formId' => $formId])
            ->andWhere(['like', 'type', 'StripeField'])
            ->all();

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

    private function removeCredentialFields(int $formId, string $formHandle): void
    {
        $fields = (new Query())
            ->select(['id', "JSON_UNQUOTE(JSON_EXTRACT([[metadata]], '$.handle')) AS [[handle]]"])
            ->from('{{%freeform_forms_fields}}')
            ->where(['formId' => $formId])
            ->andWhere(new Expression(
                "JSON_UNQUOTE(JSON_EXTRACT([[metadata]], '$.handle')) IN ('username', 'password', 'passwordConfirm')"
            ))
            ->all();

        $submissionTable = '{{%freeform_submissions_'.$formHandle.'_'.$formId.'}}';
        $table = $this->db->tableExists($submissionTable)
            ? $this->db->getTableSchema($submissionTable, true)
            : null;

        foreach ($fields as $field) {
            if ($table) {
                $column = Submission::generateFieldColumnName((int)$field['id'], (string)$field['handle']);
                if (isset($table->columns[$column])) {
                    $this->dropColumn($submissionTable, $column);
                }
            }

            $this->delete('{{%freeform_forms_fields}}', ['id' => $field['id']]);
        }
    }
}
