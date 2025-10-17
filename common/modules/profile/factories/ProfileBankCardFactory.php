<?php

declare(strict_types=1);

namespace common\modules\profile\factories;

use common\models\User;
use common\modules\profile\models\ProfileBankCard;
use common\modules\profile\models\ProfileBankCardStatusEnum;
use common\services\TimeService;
use Ramsey\Uuid\Uuid;
use Yii;

readonly class ProfileBankCardFactory
{
    public function __construct(private TimeService $timeService)
    {
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function createForUser(User $user): ProfileBankCard
    {
        $profileBankCard = Yii::createObject(ProfileBankCard::class);
        $profileBankCard->created_at = $profileBankCard->updated_at = $this->timeService->now();
        $profileBankCard->uuid = Uuid::uuid4();
        $profileBankCard->user_id = $user->id;
        $profileBankCard->status = ProfileBankCardStatusEnum::STATUS_DRAFT->value;

        return $profileBankCard;
    }
}
