<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\db\Query;

class m260909_000002_set_registration_price extends Migration
{
    public function safeUp(): bool
    {
        $fields = (new Query())
            ->select(['ff.id', 'ff.metadata'])
            ->from(['ff' => '{{%freeform_forms_fields}}'])
            ->innerJoin(['f' => '{{%freeform_forms}}'], '[[f.id]] = [[ff.formId]]')
            ->where(['f.handle' => ['userRegistration', 'mohawkvalley', 'q518']])
            ->andWhere(['like', 'ff.type', 'StripeField'])
            ->all();

        foreach ($fields as $field) {
            $metadata = json_decode((string)$field['metadata']);
            if (!$metadata) {
                continue;
            }

            $metadata->amount = 9.99;

            $this->update(
                '{{%freeform_forms_fields}}',
                ['metadata' => json_encode($metadata)],
                ['id' => $field['id']]
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
