<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\helpers\Db;
use Solspace\Freeform\Elements\Submission;

class m260809_000003_add_paid_registration_password_fields extends Migration
{
    public function safeUp(): bool
    {
        $formId = (int)$this->formId();
        if (!$formId) {
            return true;
        }

        $passwordId = $this->fieldId($formId, 'password');
        $confirmId = $this->fieldId($formId, 'passwordConfirm');

        if ($passwordId && $confirmId) {
            $this->ensureSubmissionColumns($formId, $passwordId, $confirmId);
            return true;
        }

        $layoutId = (int)(new \craft\db\Query())
            ->select(['id'])
            ->from('{{%freeform_forms_layouts}}')
            ->where(['formId' => $formId])
            ->scalar();

        if (!$layoutId) {
            return true;
        }

        $rowId = null;
        if (!$passwordId || !$confirmId) {
            $this->update('{{%freeform_forms_rows}}', [
                'order' => new \yii\db\Expression('[[order]] + 1'),
            ], ['and', ['formId' => $formId], ['>=', 'order', 2]]);

            $now = Db::prepareDateForDb(new \DateTime());
            $rowUid = \Craft::$app->getSecurity()->generateRandomString(32);

            $this->insert('{{%freeform_forms_rows}}', [
                'formId' => $formId,
                'layoutId' => $layoutId,
                'order' => 2,
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => $rowUid,
            ]);

            $rowId = (int)$this->db->getLastInsertID();
        }

        if (!$passwordId) {
            $passwordId = $this->insertField($formId, $rowId, 0, 'password', 'Password', \Craft::$app->getSecurity()->generateRandomString(32));
        }

        $passwordUid = $this->fieldUid($formId, 'password') ?: \Craft::$app->getSecurity()->generateRandomString(32);

        if (!$confirmId) {
            $confirmId = $this->insertField($formId, $rowId, 1, 'passwordConfirm', 'Confirm Password', \Craft::$app->getSecurity()->generateRandomString(32), $passwordUid);
        }

        $this->normalizeField($formId, 'password', 'Password');
        $this->normalizeField($formId, 'passwordConfirm', 'Confirm Password', $passwordUid);
        $this->ensureSubmissionColumns($formId, $passwordId, $confirmId);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260809_000003_add_paid_registration_password_fields cannot be reverted safely.\n";

        return false;
    }

    private function formId(): ?int
    {
        return (new \craft\db\Query())
            ->select(['id'])
            ->from('{{%freeform_forms}}')
            ->where(['handle' => 'userRegistration'])
            ->scalar();
    }

    private function fieldId(int $formId, string $handle): ?int
    {
        return (new \craft\db\Query())
            ->select(['id'])
            ->from('{{%freeform_forms_fields}}')
            ->where(['formId' => $formId])
            ->andWhere(new \yii\db\Expression("JSON_UNQUOTE(JSON_EXTRACT([[metadata]], '$.handle')) = :handle", ['handle' => $handle]))
            ->scalar();
    }

    private function fieldUid(int $formId, string $handle): ?string
    {
        return (new \craft\db\Query())
            ->select(['uid'])
            ->from('{{%freeform_forms_fields}}')
            ->where(['formId' => $formId])
            ->andWhere(new \yii\db\Expression("JSON_UNQUOTE(JSON_EXTRACT([[metadata]], '$.handle')) = :handle", ['handle' => $handle]))
            ->scalar();
    }

    private function ensureSubmissionColumns(int $formId, int $passwordId, int $confirmId): void
    {
        $submissionTable = '{{%freeform_submissions_user_registration_'.$formId.'}}';
        if (!$this->db->tableExists($submissionTable)) {
            return;
        }

        $table = $this->db->getTableSchema($submissionTable, true);
        $columns = [
            Submission::generateFieldColumnName($passwordId, 'password'),
            Submission::generateFieldColumnName($confirmId, 'passwordConfirm'),
        ];

        foreach ($columns as $column) {
            if (!isset($table->columns[$column])) {
                $this->addColumn($submissionTable, $column, $this->text());
            }
        }
    }

    private function insertField(int $formId, int $rowId, int $order, string $handle, string $label, string $uid, ?string $targetFieldUid = null): int
    {
        $metadata = $this->fieldMetadata($handle, $label, $targetFieldUid);

        $now = Db::prepareDateForDb(new \DateTime());

        $this->insert('{{%freeform_forms_fields}}', [
            'formId' => $formId,
            'type' => 'Solspace\\Freeform\\Fields\\Implementations\\TextField',
            'metadata' => json_encode($metadata),
            'rowId' => $rowId,
            'order' => $order,
            'dateCreated' => $now,
            'dateUpdated' => $now,
            'uid' => $uid,
        ]);

        return (int)$this->db->getLastInsertID();
    }

    private function normalizeField(int $formId, string $handle, string $label, ?string $targetFieldUid = null): void
    {
        $field = (new \craft\db\Query())
            ->select(['id', 'metadata'])
            ->from('{{%freeform_forms_fields}}')
            ->where(['formId' => $formId])
            ->andWhere(new \yii\db\Expression("JSON_UNQUOTE(JSON_EXTRACT([[metadata]], '$.handle')) = :handle", ['handle' => $handle]))
            ->one();

        if (!$field) {
            return;
        }

        $metadata = json_decode((string)$field['metadata'], true) ?: [];
        $metadata = array_replace_recursive($metadata, $this->fieldMetadata($handle, $label, $targetFieldUid));

        $this->update('{{%freeform_forms_fields}}', [
            'type' => 'Solspace\\Freeform\\Fields\\Implementations\\TextField',
            'metadata' => json_encode($metadata),
            'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
        ], ['id' => $field['id']]);
    }

    private function fieldMetadata(string $handle, string $label, ?string $targetFieldUid = null): array
    {
        $metadata = [
            'label' => $label,
            'handle' => $handle,
            'required' => true,
            'encrypted' => true,
            'fieldType' => null,
            'maxLength' => null,
            'attributes' => [
                'error' => (object)[],
                'input' => [
                    'type' => 'password',
                    'autocomplete' => 'new-password',
                ],
                'label' => (object)[],
                'option' => (object)[],
                'container' => (object)[],
                'optionLabel' => (object)[],
                'instructions' => (object)[],
            ],
            'placeholder' => '',
            'defaultValue' => null,
            'instructions' => '',
            'requiredMessage' => null,
        ];

        if ($targetFieldUid) {
            $metadata['targetField'] = $targetFieldUid;
        }

        return $metadata;
    }
}
