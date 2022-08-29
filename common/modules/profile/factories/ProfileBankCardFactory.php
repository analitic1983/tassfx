<?php

namespace common\modules\profile\factories;

use common\helpers\DateHelper;
use common\models\User;
use common\modules\profile\models\ProfileBankCard;
use Ramsey\Uuid\Uuid;
use Yii;

class ProfileBankCardFactory
{
    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function createForUser(User $user): ProfileBankCard
    {
        $profileBankCard = Yii::createObject(ProfileBankCard::class);
        $profileBankCard->created_at = $profileBankCard->updated_at = DateHelper::now();
        $profileBankCard->uuid = Uuid::uuid4();
        $profileBankCard->user_id = $user->id;
        $profileBankCard->status = ProfileBankCard::STATUS_DRAFT;
        return $profileBankCard;
    }
}