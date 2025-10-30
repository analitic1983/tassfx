<?php


declare(strict_types=1);

namespace common\modules\profile\models;

use common\modules\profile\models\ProfileBankCard;
use common\modules\profile\models\ProfileBankCardStatusEnum;
use yii\data\ActiveDataProvider;

class ProfileBankCardSearch extends ProfileBankCard
{
    public function rules(): array
    {
        return [
            [['name'], 'safe'],
        ];
    }
    public function search(array $params = []): ActiveDataProvider
    {
        $query = ProfileBankCard::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if ($this->user_id) {
            $query->andWhere(['user_id' => $this->user_id]);
        }

        if (!$this->validate()) {
            $query->emulateExecution();

            return $dataProvider;
        }

        $query->andWhere(['status' => ProfileBankCardStatusEnum::activeStatusList()]);

        return $dataProvider;
    }
}
