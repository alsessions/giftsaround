<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use Solspace\Freeform\Elements\Submission;

class m260809_000004_remove_registration_password_fields extends Migration
{
    public function safeUp(): bool
    {
        $formId = (int)(new \craft\db\Query())
            ->select(['id'])
            ->from('{{%freeform_forms}}')
            ->where(['handle' => 'userRegistration'])
            ->scalar();

        if (!$formId) {
            return true;
        }

        $fields = (new \craft\db\Query())
            ->select(['id', "JSON_UNQUOTE(JSON_EXTRACT([[metadata]], '$.handle')) AS [[handle]]"])
            ->from('{{%freeform_forms_fields}}')
            ->where(['formId' => $formId])
            ->andWhere(new \yii\db\Expression("JSON_UNQUOTE(JSON_EXTRACT([[metadata]], '$.handle')) IN ('password', 'passwordConfirm')"))
            ->all();

        $submissionTable = '{{%freeform_submissions_user_registration_'.$formId.'}}';
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

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260809_000004_remove_registration_password_fields cannot be reverted safely.\n";

        return false;
    }
}
