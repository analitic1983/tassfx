<?php

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
    public const STATUS_DRAFT                = 'draft';
    public const STATUS_WAIT_FOR_APPROVE     = 'waitForApprove';
    public const STATUS_APPROVED             = 'approved';
    public const STATUS_DECLINED             = 'declined';
    public const STATUS_DELETED              = 'deleted';
    public const STATUS_DELETED_BY_MODERATOR = 'deletedByModerator';

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
     * This bank card available for client
     *
     * @return string[]
     */
    public static function activeStatusList(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_WAIT_FOR_APPROVE,
            self::STATUS_APPROVED,
            self::STATUS_DECLINED
        ];
    }

    public static function allStatusList(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_WAIT_FOR_APPROVE,
            self::STATUS_APPROVED,
            self::STATUS_DECLINED,
            self::STATUS_DELETED,
            self::STATUS_DELETED_BY_MODERATOR,
        ];
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_DRAFT                => Yii::t('frontend', 'draft'),
            self::STATUS_WAIT_FOR_APPROVE     => Yii::t('frontend', 'wait for approve'),
            self::STATUS_APPROVED             => Yii::t('frontend', 'approved'),
            self::STATUS_DECLINED             => Yii::t('frontend', 'declined'),
            self::STATUS_DELETED              => Yii::t('frontend', 'deleted'),
            self::STATUS_DELETED_BY_MODERATOR => Yii::t('frontend', 'deleted by moderator'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
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
            ['status', 'in', 'range' => static::allStatusList()],
        ];
    }

    public function validateLastDigits()
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
        return in_array($this->status, static::activeStatusList());
    }

    public function isDraft(): bool
    {
        return $this->status == self::STATUS_DRAFT;
    }

    public function isWaitingForConfirm(): bool
    {
        return $this->status == self::STATUS_WAIT_FOR_APPROVE;
    }

    public function isApproved(): bool
    {
        return $this->status == self::STATUS_APPROVED;
    }

    public function isDeclined(): bool
    {
        return $this->status == self::STATUS_DECLINED;
    }

    public function isAllowChange(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_WAIT_FOR_APPROVE]);
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


    public function getTitle()
    {
        return Yii::t('frontend', 'Bank Card {name}', ['name' => $this->last_digits]);
    }

    public function getStatusLabel(): string
    {
        return $this->status ? $this->getStatusLabels()[$this->status] : '';
    }

    /**
     * Gets query for [[User]].
     *
     * @return ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}