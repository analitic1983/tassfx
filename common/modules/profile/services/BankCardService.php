<?php

declare(strict_types=1);

namespace common\modules\profile\services;

use common\components\FilesConnector;
use common\helpers\DateHelper;
use common\interfaces\PusherInterface;
use common\models\User;
use common\modules\file\exceptions\FileConnectorException;
use common\modules\file\managers\FileCacheManager;
use common\modules\file\models\File;
use common\modules\profile\factories\ProfileBankCardFactory;
use common\modules\profile\models\ProfileBankCard;
use common\modules\profile\models\ProfileBankCardStatusEnum;
use common\modules\profile\populators\BankCardPopulator;
use common\modules\profile\pushers\ClientProfileCrmPusher;
use Yii;
use yii\base\UserException;
use yii\web\UploadedFile;

readonly class BankCardService
{
    public function __construct(
        private BankCardPopulator      $bankCardPopulator,
        private ProfileBankCardFactory $bankCardFactory,
        private FileCacheManager       $fileCacheManager,
        private ClientProfileCrmPusher $crmBankCardPusher,
        private FilesConnector         $filesServer,
        private TimeService            $timeService,
    )
    {
    }

    /**
     * @param ProfileBankCard $bankCard
     * @param array $data
     * @return void
     * @throws \common\exceptions\InvalidModelException
     * @throws \common\exceptions\ValidationException
     * @throws \common\modules\crm\exceptions\CrmPlugApiException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function updateByData(ProfileBankCard $bankCard, array $data): void
    {
        $this->bankCardPopulator->populate($bankCard, $data);
        $bankCard->status = ProfileBankCardStatusEnum::STATUS_WAIT_FOR_APPROVE->value;
        $this->update($bankCard);
    }

    /**
     * @param ProfileBankCard $bankCard
     * @return void
     * @throws \common\exceptions\InvalidModelException
     * @throws \common\exceptions\ValidationException
     * @throws \common\modules\crm\exceptions\CrmPlugApiException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function update(ProfileBankCard $bankCard): void
    {
        $bankCard->updated_at = $this->timeService->now();
        $bankCard->validateOrFail();
        $bankCard->saveOrFail();
        if (!$bankCard->isDraft()){
            $this->crmBankCardPusher->syncBankCard($bankCard, PusherInterface::ASYNC_MODE);
        }
    }

    /**
     * Сreate bank card
     *
     * @throws \common\exceptions\NoAccessForUserException
     * @throws \yii\base\InvalidConfigException
     * @throws \common\exceptions\ValidationException
     * @throws \common\exceptions\InvalidModelException
     * @throws \common\exceptions\NoAccessException
     */
    public function create(string $bankCardUuid, User $user): ProfileBankCard
    {
        $bankCard = $this->bankCardFactory->createForUser($user);
        $bankCard->uuid = $bankCardUuid;
        $this->update($bankCard);
        return $bankCard;
    }

    protected function getFile(ProfileBankCard $bankCard, string $fileName): File
    {
        $cachedFile = $this->fileCacheManager->getCachedFile($fileName);
        if ($cachedFile) {
            return $cachedFile;
        }

        $dir = $this->filesServer->createDir('user-' . sprintf('%010d', $bankCard->user->id));
        if (!$dir) {
            throw new FileConnectorException('Not exists: '.$dir);
        }
        $fileContent = $this->filesServer->viewFile($dir, $fileName);
        $file = $this->fileCacheManager->storeUrlInCache($fileContent, $fileName);
        return $file;
    }

    public function getFrontFile(ProfileBankCard $bankCard): File
    {
        return $this->getFile($bankCard, $bankCard->front_photo_file);
    }

    /**
     * @param ProfileBankCard $bankCard
     * @param UploadedFile $file
     * @throws FileConnectorException
     * @throws \common\exceptions\InvalidModelException
     * @throws \common\exceptions\ValidationException
     * @throws \common\modules\crm\exceptions\CrmPlugApiException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    public function setFrontFile(ProfileBankCard $bankCard, UploadedFile $file): void
    {
        if ($bankCard->front_photo_file) {
            $this->deleteFrontPhoto($bankCard);
        }

        $fileName = 'FrontCard_' . $bankCard->uuid . '_' . md5($file->getBaseName()) . '.' . $file->extension;

        $dir = $this->filesServer->createDir('user-' . sprintf('%010d', $bankCard->user->id), $bankCard->user->email);
        $response = $this->filesServer->uploadFile($file, $dir, $fileName);

        if (empty($response->name)) {
            throw new FileConnectorException('Can\'t upload front card file');
        }

        $bankCard->front_photo_file = $fileName;
        $this->update($bankCard);
    }

    /**
     * @throws \yii\base\InvalidConfigException
     * @throws \common\modules\crm\exceptions\CrmPlugApiException
     * @throws \yii\httpclient\Exception
     * @throws \common\exceptions\InvalidModelException
     * @throws \common\exceptions\ValidationException
     */
    public function deleteFrontPhoto(ProfileBankCard $bankCard): void
    {
        if (empty($bankCard->front_photo_file)) {
            // Already deleted;
            return;
        }
        $dir = $this->filesServer->createDir('user-' . sprintf('%010d', $bankCard->user->id));
        $this->filesServer->deleteFile($dir, $bankCard->front_photo_file);
        $bankCard->front_photo_file = "";
        $this->update($bankCard);
    }

    /**
     * @throws FileConnectorException
     */
    public function getBackFile(ProfileBankCard $bankCard): File
    {
        return $this->getFile($bankCard, $bankCard->back_photo_file);
    }

    /**
     * @throws FileConnectorException
     * @throws \yii\base\InvalidConfigException
     * @throws \common\modules\crm\exceptions\CrmPlugApiException
     * @throws \yii\httpclient\Exception
     * @throws \common\exceptions\InvalidModelException
     * @throws \common\exceptions\ValidationException
     */
    public function setBackFile(ProfileBankCard $bankCard, UploadedFile $file): void
    {
        if ($bankCard->back_photo_file) {
            $this->deleteBackPhoto($bankCard);
        }

        $fileName = 'BackCard_' . $bankCard->uuid . '_' . md5($file->getBaseName()) . '.' . $file->extension;

        $dir = $this->filesServer->createDir('user-' . sprintf('%010d', $bankCard->user->id), $bankCard->user->email);
        $response = $this->filesServer->uploadFile($file, $dir, $fileName);

        if (empty($response->name)) {
            throw new FileConnectorException('Can\'t upload back card file');
        }

        $bankCard->back_photo_file = $fileName;
        $this->update($bankCard);
    }

    /**
     * @throws \yii\base\InvalidConfigException
     * @throws \common\modules\crm\exceptions\CrmPlugApiException
     * @throws \yii\httpclient\Exception
     * @throws \common\exceptions\InvalidModelException
     * @throws \common\exceptions\ValidationException
     */
    public function deleteBackPhoto(ProfileBankCard $bankCard): void
    {
        if (empty($bankCard->back_photo_file)) {
            // Already deleted;
            return;
        }
        $dir = $this->filesServer->createDir('user-' . sprintf('%010d', $bankCard->user->id));
        $this->filesServer->deleteFile($dir, $bankCard->back_photo_file);
        $bankCard->back_photo_file = "";
        $this->update($bankCard);
    }

    /**
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\httpclient\Exception
     * @throws \common\modules\crm\exceptions\CrmPlugApiException
     * @throws \common\exceptions\InvalidModelException
     * @throws \common\exceptions\ValidationException
     */
    public function deleteCard(ProfileBankCard $bankCard): void
    {
        $bankCard->status = ProfileBankCardStatusEnum::STATUS_DELETED;
        $this->update($bankCard);
    }

}
