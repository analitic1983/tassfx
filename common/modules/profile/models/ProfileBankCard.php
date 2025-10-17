<?php

declare(strict_types=1);

namespace common\modules\profile\models;

use common\components\BaseActiveRecord;
use common\exceptions\NoAccessException;
use common\models\User;
use common\modules\profile\queries\ProfileBankCardQuery;
use Yii;
use yii\db\ActiveQuery;

/**
 *
 * @property string $uuid
 * @property int $user_id
 * @property string $last_digits
 * @property string $name
 * @property string created_at
 * @property string $updated_at
 * @property string $front_photo_file
 * @property string $back_photo_file
 * @property string $status
 *
 * @property User $user
 */
class ProfileBankCard extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%profile_bank_cards}}';
    }

    public static function find()
    {
        return new ProfileBankCardQuery(self::class);
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['uuid', 'user_id', 'created_at', 'updated_at'], 'required'],
            [
                ['last_digits', 'name', 'front_photo_file'], 'required', 'when' => function (ProfileBankCard $model) {
                return $model->isWaitingForConfirm();
            }
            ],
            [['user_id'], 'integer'],
            [['last_digits'], 'string', 'min' => 4, 'max' => 4],
            ['last_digits', 'validateLastDigits'],
            [['uuid', 'name', 'created_at', 'updated_at', 'status', 'front_photo_file', 'back_photo_file'], 'string'],
            ['status', 'in', 'range' => ProfileBankCardStatusEnum::getAllList()],
        ];
    }

    public function validateLastDigits(): void
    {
        if (!ctype_digit($this->last_digits)) {
            $this->addError('last_digits', Yii::t('frontend', 'Bank card number should have only digits'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'               => Yii::t('frontend', 'ID'),
            'front_photo_file' => Yii::t('frontend', 'Bank card front foto'),
            'back_photo_file'  => Yii::t('frontend', 'Bank card back foto'),
            'last_digits'      => Yii::t('frontend', 'Last bank 4 digits'),
            'name'             => Yii::t('frontend', 'Name'),
            'status'           => Yii::t('frontend', 'Status'),
        ];
    }

    public function isActive(): bool
    {
        $status = ProfileBankCardStatusEnum::tryFrom($this->status);
        return $status && in_array($status, ProfileBankCardStatusEnum::activeStatusList(), true);
    }

    public function isDraft(): bool
    {
        return $this->status == ProfileBankCardStatusEnum::STATUS_DRAFT->value;
    }

    public function isWaitingForConfirm(): bool
    {
        return $this->status == ProfileBankCardStatusEnum::STATUS_WAIT_FOR_APPROVE->value;
    }

    public function isApproved(): bool
    {
        return $this->status == ProfileBankCardStatusEnum::STATUS_APPROVED->value;
    }

    public function isDeclined(): bool
    {
        return $this->status == ProfileBankCardStatusEnum::STATUS_DECLINED->value;
    }

    public function isAllowChange(): bool
    {
        $status = ProfileBankCardStatusEnum::tryFrom($this->status);
        return in_array($status, [ProfileBankCardStatusEnum::STATUS_DRAFT, ProfileBankCardStatusEnum::STATUS_WAIT_FOR_APPROVE], true);
    }

    /**
     * @return void
     * @throws NoAccessException
     */
    public function checkIsAllowChangeOrFail(): void
    {
        if (!$this->isAllowChange()) {
            throw new NoAccessException("Not allowed to change Profile bank card " . $this->uuid);
        }
    }


    public function getTitle(): string
    {
        return Yii::t('frontend', 'Bank Card {name}', ['name' => $this->last_digits]);
    }

    public function getStatusLabel(): string
    {
        return $this->status ? ProfileBankCardStatusEnum::getLabel()[$this->status] : '';
    }

    /**
     * Gets query for [[User]].
     *
     * @return ActiveQuery
     */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
