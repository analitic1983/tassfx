<?php

namespace common\modules\profile\models;

use common\enum\trait\NameMapEnumTrait;
use Yii;

enum ProfileBankCardStatusEnum: string
{
    use NameMapEnumTrait;

    case STATUS_DRAFT = 'draft';
    case STATUS_WAIT_FOR_APPROVE = 'waitForApprove';
    case STATUS_APPROVED = 'approved';
    case STATUS_DECLINED = 'declined';
    case STATUS_DELETED = 'deleted';
    case STATUS_DELETED_BY_MODERATOR = 'deletedByModerator';

    public static function getCodeNameMap(): array
    {
        return [

            self::STATUS_DRAFT->value                => Yii::t('frontend', 'draft'),
            self::STATUS_WAIT_FOR_APPROVE->value     => Yii::t('frontend', 'wait for approve'),
            self::STATUS_APPROVED->value             => Yii::t('frontend', 'approved'),
            self::STATUS_DECLINED->value             => Yii::t('frontend', 'declined'),
            self::STATUS_DELETED->value              => Yii::t('frontend', 'deleted'),
            self::STATUS_DELETED_BY_MODERATOR->value => Yii::t('frontend', 'deleted by moderator'),
        ];
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
}

