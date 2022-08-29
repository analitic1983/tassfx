<?php

namespace frontend\controllers\personal;

use common\components\UploadedFile;
use common\exceptions\InvalidModelException;
use common\exceptions\ModelNotFoundException;
use common\exceptions\NoAccessException;
use common\exceptions\NoAccessForUserException;
use common\exceptions\ValidationException;
use common\modules\profile\factories\ProfileBankCardFactory;
use common\modules\profile\helpers\BankCardUrlHelper;
use common\modules\profile\helpers\ProfileUrlHelper;
use common\modules\profile\models\ProfileBankCard;
use common\modules\profile\services\BankCardService;
use Da\User\Filter\AccessRuleFilter;
use frontend\controllers\AppController;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Response;

class CardController extends AppController
{
    protected ProfileBankCardFactory $bankCardFactory;
    protected BankCardService        $bankCardService;

    public function __construct($id, $module, $config = [], ProfileBankCardFactory $profileBankCardRepository, BankCardService $bankCardService)
    {
        $this->bankCardFactory = $profileBankCardRepository;
        $this->bankCardService = $bankCardService;
        parent::__construct($id, $module, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
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
                    'delete-front-photo' => ['POST']
                ]
            ]
        ];
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function actionCreateModal(): string
    {
        $bankCard = $this->bankCardFactory->createForUser($this->getCurrentUser());
        return $this->renderAdaptive('updateModal', [
            'bankCard' => $bankCard,
        ]);
    }

    /**
     * @throws ModelNotFoundException
     * @throws NoAccessForUserException
     */
    public function actionUpdateModal($uuid): string
    {
        $bankCard = ProfileBankCard::findByPkOrFail($uuid);
        $bankCard->checkIsOwnerOrFail($this->getCurrentUser());
        return $this->renderAdaptive('updateModal', [
            'bankCard' => $bankCard,
        ]);
    }

    /**
     * @throws ModelNotFoundException
     * @throws InvalidModelException
     * @throws ValidationException
     * @throws NoAccessException
     * @throws NoAccessForUserException
     */
    public function actionSave(string $uuid): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        $bankCard = $this->bankCardService->getOrCreate($uuid, $this->getCurrentUser());
        $this->bankCardService->updateByData($bankCard, Yii::$app->request->post());
        Yii::$app->session->setFlash('success', \Yii::t('frontend', 'Bank card saved'));
        return $this->jsonSuccess([
            'reload' => true,
        ]);
    }

    /**
     * @throws ModelNotFoundException
     * @throws NoAccessForUserException
     */
    public function actionDelete(string $uuid): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        $bankCard = ProfileBankCard::findByPkOrFail($uuid);
        $bankCard->checkIsOwnerOrFail($this->getCurrentUser());
        $this->bankCardService->deleteCard($bankCard);
        Yii::$app->session->setFlash('success', \Yii::t('frontend', 'Bank card deleted'));
        return $this->redirect(ProfileUrlHelper::showProfile('bankCards'));
    }


    /**
     * @throws ModelNotFoundException
     * @throws NoAccessForUserException
     */
    public function actionFrontPhoto(string $bankCardUuid): void
    {
        $bankCard = ProfileBankCard::findByPkOrFail($bankCardUuid);
        $bankCard->checkIsOwnerOrFail($this->getCurrentUser());
        $file = $this->bankCardService->getFrontFile($bankCard);

        Yii::$app->response->headers->set('Cache-Control', 'max-age=360');
        Yii::$app->response->sendFile($file->getPath(), $file->getFileName(), ['inline' => true]);
    }

    /**
     * @return Response
     * @throws InvalidModelException
     * @throws NoAccessForUserException
     * @throws ValidationException
     * @throws \common\modules\file\exceptions\FileConnectorException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionUploadFrontPhoto(): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        $bankCardUuid = Yii::$app->request->post('bankCardUuid');
        $bankCard = $this->bankCardService->getOrCreate($bankCardUuid, $this->getCurrentUser());
        $file = UploadedFile::getInstance($bankCard, 'frontPhoto');
        if (!$file) {
            // Strange design from FileInput::widget: run upload without file?
            return $this->jsonSuccessMessage('No files uploaded');
        }
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

    public function actionDeleteFrontPhoto(): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        $bankCardUuid = Yii::$app->request->post('bankCardUuid');
        $bankCard = ProfileBankCard::findByPkOrFail($bankCardUuid);
        $this->bankCardService->deleteFrontPhoto($bankCard);
        return $this->jsonSuccessMessage(Yii::t('frontend', "Deleted"));
    }

    /**
     * @throws ModelNotFoundException
     * @throws \common\modules\file\exceptions\FileConnectorException
     * @throws NoAccessForUserException
     */
    public function actionBackPhoto(string $bankCardUuid)
    {
        $bankCard = ProfileBankCard::findByPkOrFail($bankCardUuid);
        $bankCard->checkIsOwnerOrFail($this->getCurrentUser());
        $file = $this->bankCardService->getBackFile($bankCard);
        Yii::$app->response->sendFile($file->getPath(), $file->getFileName(), ['inline' => true]);
    }

    /**
     * @return Response
     * @throws InvalidModelException
     * @throws NoAccessForUserException
     * @throws ValidationException
     * @throws \common\modules\file\exceptions\FileConnectorException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionUploadBackPhoto(): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        $bankCardUuid = Yii::$app->request->post('bankCardUuid');
        $bankCard = $this->bankCardService->getOrCreate($bankCardUuid, $this->getCurrentUser());
        $file = UploadedFile::getInstance($bankCard, 'backPhoto');
        if (!$file) {
            // Strange design from FileInput::widget: run upload without file?
            return $this->jsonSuccessMessage('No files uploaded');
        }
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

    public function actionDeleteBackPhoto(): Response
    {
        $this->response->format = Response::FORMAT_JSON;
        $bankCardUuid = Yii::$app->request->post('bankCardUuid');
        $bankCard = ProfileBankCard::findByPkOrFail($bankCardUuid);
        $this->bankCardService->deleteBackPhoto($bankCard);
        return $this->jsonSuccessMessage(Yii::t('frontend', "Deleted"));
    }
}