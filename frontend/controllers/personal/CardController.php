<?php

declare(strict_types=1);

namespace frontend\controllers\personal;

use common\components\UploadedFile;
use common\exceptions\InvalidModelException;
use common\exceptions\ModelNotFoundException;
use common\exceptions\NoAccessException;
use common\exceptions\NoAccessForUserException;
use common\exceptions\ValidationException;
use common\modules\file\exceptions\FileConnectorException;
use common\modules\profile\factories\ProfileBankCardFactory;
use common\modules\profile\helpers\BankCardUrlHelper;
use common\modules\profile\helpers\ProfileUrlHelper;
use common\modules\profile\models\ProfileBankCard;
use common\modules\profile\services\BankCardService;
use Da\User\Filter\AccessRuleFilter;
use frontend\controllers\AppController;
use Yii;
use yii\base\InvalidConfigException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Response;

class CardController extends AppController
{
    public function __construct(
        $id,
        $module,
        $config = [],
        private readonly ProfileBankCardFactory $bankCardFactory,
        private readonly BankCardService $bankCardService)
    {
        parent::__construct($id, $module, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class'      => AccessControl::class,
                'ruleConfig' => [
                    'class' => AccessRuleFilter::class,
                ],
                'rules'      => [
                    [
                        'allow'   => true,
                        'actions' => [
                            'create-modal', 'update-modal', 'delete', 'save',
                            'front-photo', 'upload-front-photo', 'delete-front-photo',
                            'back-photo', 'upload-back-photo', 'delete-back-photo',
                        ],
                        'roles'   => ['@'],
                    ],
                ],
            ],
            'verbs'  => [
                'class'   => VerbFilter::class,
                'actions' => [
                    'save'               => ['POST'],
                    'delete'             => ['POST'],
                    'upload-front-photo' => ['POST'],
                    'delete-front-photo' => ['POST'],
                    'upload-back-photo'  => ['POST'],
                    'delete-back-photo'  => ['POST']
                ]
            ]
        ];
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function actionCreateModal(): string
    {
        $bankCard = $this->bankCardFactory->createForUser($this->getCurrentUser());
        return $this->renderAdaptive('updateModal', [
            'bankCard' => $bankCard,
        ]);
    }

    /**
     * @return string
     * @throws ModelNotFoundException
     * @throws NoAccessForUserException
     */
    public function actionUpdateModal($uuid): string
    {
        $bankCard = $this->getBankCard($uuid);
        return $this->renderAdaptive('updateModal', [
            'bankCard' => $bankCard,
        ]);
    }

    /**
     * @return Response
     * @throws ModelNotFoundException
     * @throws InvalidModelException
     * @throws ValidationException
     * @throws NoAccessException
     * @throws NoAccessForUserException
     */
    public function actionSave(string $uuid): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        $bankCard = $this->getBankCard($uuid);
        $bankCard->checkIsAllowChangeOrFail();
        $this->bankCardService->updateByData($bankCard, Yii::$app->request->post());
        Yii::$app->session->setFlash('success', Yii::t('frontend', 'Bank card saved'));
        return $this->jsonSuccess([
            'reload' => true,
        ]);
    }

    /**
     * @return Response
     * @throws ModelNotFoundException
     * @throws NoAccessForUserException
     * @throws NoAccessException
     */
    public function actionDelete(string $uuid): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        $bankCard = $this->getBankCard($uuid);
        $bankCard->checkIsAllowChangeOrFail();
        $this->bankCardService->deleteCard($bankCard);
        Yii::$app->session->setFlash('success', Yii::t('frontend', 'Bank card deleted'));
        return $this->jsonSuccess([
            'redirect' => ProfileUrlHelper::showProfileBankCards()
        ]);
    }


    /**
     * @throws ModelNotFoundException
     * @throws NoAccessForUserException
     */
    public function actionFrontPhoto(string $uuid): void
    {
        $bankCard = $this->getBankCard($uuid);
        $file = $this->bankCardService->getFrontFile($bankCard);

        Yii::$app->response->headers->set('Cache-Control', 'max-age=360');
        Yii::$app->response->sendFile($file->getPath(), $file->getFileName(), ['inline' => true]);
    }

    /**
     * @return Response
     * @throws InvalidModelException
     * @throws NoAccessForUserException
     * @throws ValidationException
     * @throws FileConnectorException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionUploadFrontPhoto(): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        $bankCardUuid = Yii::$app->request->post('bankCardUuid');
        $file = UploadedFile::getInstanceByName('frontPhoto');
        if ($file === null) {
            // Strange design from FileInput::widget: run upload without file?
            return $this->jsonSuccessMessage('No files uploaded');
        }
        $bankCard = $this->getBankCard($bankCardUuid);
        $bankCard->checkIsAllowChangeOrFail();
        $this->bankCardService->setFrontFile($bankCard, $file);
        return $this->jsonSuccess([
            'initialPreview'       => [BankCardUrlHelper::viewFrontPhotoUrl($bankCard)],
            'initialPreviewConfig' => [
                [
                    'key'     => $bankCard->uuid,
                    'caption' => $file->getBaseName(),
                    'size'    => $file->size,
                ]
            ],
            'initialPreviewAsData' => true,
            'message'              => Yii::t('frontend', "Uploaded")
        ]);
    }

    /**
     * @return Response
     * @throws InvalidModelException
     * @throws NoAccessForUserException
     * @throws ValidationException
     * @throws FileConnectorException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionDeleteFrontPhoto(): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        $bankCardUuid = Yii::$app->request->post('bankCardUuid');
        $bankCard = $this->getBankCard($bankCardUuid);
        $bankCard->checkIsAllowChangeOrFail();
        $this->bankCardService->deleteFrontPhoto($bankCard);
        return $this->jsonSuccessMessage(Yii::t('frontend', "Deleted"));
    }

    /**
     * @throws ModelNotFoundException
     * @throws FileConnectorException
     * @throws NoAccessForUserException
     */
    public function actionBackPhoto(string $bankCardUuid): void
    {
        $bankCard = $this->getBankCard($bankCardUuid);
        $file = $this->bankCardService->getBackFile($bankCard);
        Yii::$app->response->headers->set('Cache-Control', 'max-age=360');
        Yii::$app->response->sendFile($file->getPath(), $file->getFileName(), ['inline' => true]);
    }

    /**
     * @return Response
     * @throws InvalidModelException
     * @throws NoAccessForUserException
     * @throws ValidationException
     * @throws FileConnectorException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionUploadBackPhoto(): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        $bankCardUuid = Yii::$app->request->post('bankCardUuid');
        $file = UploadedFile::getInstanceByName('backPhoto');
        if ($file === null) {
            // Strange design from FileInput::widget: run upload without file?
            return $this->jsonSuccessMessage('No files uploaded');
        }
        $bankCard = $this->getBankCard($bankCardUuid);
        $bankCard->checkIsAllowChangeOrFail();
        $this->bankCardService->setBackFile($bankCard, $file);
        return $this->jsonSuccess([
            'initialPreview'       => [BankCardUrlHelper::viewBackPhotoUrl($bankCard)],
            'initialPreviewConfig' => [
                [
                    'key'     => $bankCard->uuid,
                    'caption' => $file->getBaseName(),
                    'size'    => $file->size,
                ]
            ],
            'initialPreviewAsData' => true,
            'message'              => Yii::t('frontend', "Uploaded")
        ]);
    }

    /**
     * @return Response
     * @throws InvalidModelException
     * @throws NoAccessForUserException
     * @throws ValidationException
     * @throws \common\modules\file\exceptions\FileConnectorException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionDeleteBackPhoto(): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        $bankCardUuid = Yii::$app->request->post('bankCardUuid');
        $bankCard = $this->getBankCard($bankCardUuid);
        $bankCard->checkIsAllowChangeOrFail();
        $this->bankCardService->deleteBackPhoto($bankCard);
        return $this->jsonSuccessMessage(Yii::t('frontend', "Deleted"));
    }

    /**
     * @return ProfileBankCard
     * @throws ModelNotFoundException
     * @throws NoAccessForUserException
     */
    private function getBankCard(string  $uuid): ProfileBankCard
    {
        $bankCard = ProfileBankCard::findByPkOrFail($uuid);
        $bankCard->checkIsOwnerOrFail($this->getCurrentUser());
        return $bankCard;
    }
}
