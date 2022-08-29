<?php

namespace common\modules\profile\populators;

use common\modules\profile\models\ProfileBankCard;
use common\populators\ArrayPopulatorTrait;

class BankCardPopulator
{
    use ArrayPopulatorTrait;

    public function populate(ProfileBankCard $bankCard, array $data): void
    {
        $formData = $data;
        if (!empty($data[$bankCard->formName()])) {
            $formData = $data[$bankCard->formName()];
        }
        $this->populateAttributes($bankCard, $formData, [
            'last_digits',
            'name',
        ]);
    }
}