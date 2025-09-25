<?php

namespace modules\redeem\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use craft\records\User;
use yii\db\ActiveQueryInterface;

/**
 * RedeemToken record
 *
 * @property int $id
 * @property string $token
 * @property int $userId
 * @property int $businessId
 * @property string $redeemType
 * @property int|null $monthIndex
 * @property string|null $monthData
 * @property string $expiresAt
 * @property string|null $usedAt
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class RedeemTokenRecord extends ActiveRecord
{
    // Constants
    // =========================================================================

    const REDEEM_TYPE_ONE_SPECIAL = 'oneSpecial';
    const REDEEM_TYPE_MONTHLY_SPECIAL = 'monthlySpecial';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%redeem_tokens}}';
    }

    /**
     * Returns the user associated with this redeem token.
     *
     * @return ActiveQueryInterface
     */
    public function getUser(): ActiveQueryInterface
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }

    /**
     * Returns the business entry associated with this redeem token.
     *
     * @return ActiveQueryInterface
     */
    public function getBusiness(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'businessId']);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['token', 'userId', 'businessId', 'redeemType', 'expiresAt', 'dateCreated', 'dateUpdated', 'uid'], 'required'],
            [['token'], 'string', 'length' => 64],
            [['token'], 'unique'],
            [['userId', 'businessId', 'monthIndex'], 'integer'],
            [['redeemType'], 'in', 'range' => [self::REDEEM_TYPE_ONE_SPECIAL, self::REDEEM_TYPE_MONTHLY_SPECIAL]],
            [['monthData', 'expiresAt', 'usedAt', 'dateCreated', 'dateUpdated', 'uid'], 'string'],
        ];
    }

    /**
     * Check if the token has expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return strtotime($this->expiresAt) <= time();
    }

    /**
     * Check if the token has been used
     *
     * @return bool
     */
    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    /**
     * Check if the token is valid (not expired and not used)
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    /**
     * Mark the token as used
     *
     * @return bool
     */
    public function markAsUsed(): bool
    {
        $this->usedAt = date('Y-m-d H:i:s');
        return $this->save();
    }
}
