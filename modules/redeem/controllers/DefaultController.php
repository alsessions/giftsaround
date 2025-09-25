<?php

namespace modules\redeem\controllers;

use Craft;
use craft\elements\Entry;
use craft\helpers\StringHelper;
use craft\web\Controller;
use modules\redeem\records\RedeemTokenRecord;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Default controller for redeem functionality
 */
class DefaultController extends Controller
{
    /**
     * @var array
     */
    protected array|bool|int $allowAnonymous = ['test'];

    /**
     * Simple test action to verify module routing
     *
     * @return Response
     */
    public function actionTest(): Response
    {
        return $this->asJson([
            'success' => true,
            'message' => 'Redeem module is working!',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Debug form submission action
     *
     * @return Response
     */
    public function actionDebugForm(): Response
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        return $this->asJson([
            'method' => $request->getMethod(),
            'isPost' => $request->getIsPost(),
            'bodyParams' => $request->getBodyParams(),
            'user' => Craft::$app->getUser()->getIdentity() ? [
                'id' => Craft::$app->getUser()->getId(),
                'email' => Craft::$app->getUser()->getIdentity()->email
            ] : null,
            'csrfTokenValid' => $request->getCsrfToken() ? true : false,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Generate a redeem token
     *
     * @return Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionGenerateRedeemToken(): Response
    {
        // Basic validation first
        if (!Craft::$app->getRequest()->getIsPost()) {
            Craft::$app->getSession()->setError('Invalid request method');
            return $this->goHome();
        }

        if (!Craft::$app->getUser()->getIdentity()) {
            Craft::$app->getSession()->setError('You must be logged in to redeem offers');
            return $this->redirect('/login');
        }

        try {
            $request = Craft::$app->getRequest();
            $businessId = $request->getBodyParam('businessId');
            $redeemType = $request->getBodyParam('redeemType');
            $monthIndex = $request->getBodyParam('monthIndex');
            $monthData = $request->getBodyParam('monthData');
            $userId = Craft::$app->getUser()->getId();

            // Validate required parameters
            if (!$businessId || !$redeemType) {
                Craft::$app->getSession()->setError('Missing required parameters');
                return $this->redirectToPostedUrl();
            }

            // Validate business exists
            $business = Entry::find()->id($businessId)->one();
            if (!$business) {
                Craft::$app->getSession()->setError('Business not found');
                return $this->redirectToPostedUrl();
            }

            // Generate token directly with database insert to avoid ActiveRecord issues
            $token = Craft::$app->getSecurity()->generateRandomString(32);
            $now = date('Y-m-d H:i:s');
            $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60));

            $result = Craft::$app->db->createCommand()
                ->insert('{{%redeem_tokens}}', [
                    'token' => $token,
                    'userId' => $userId,
                    'businessId' => $businessId,
                    'redeemType' => $redeemType,
                    'monthIndex' => $monthIndex,
                    'monthData' => $monthData,
                    'expiresAt' => $expiresAt,
                    'dateCreated' => $now,
                    'dateUpdated' => $now,
                    'uid' => StringHelper::UUID(),
                ])
                ->execute();

            if ($result) {
                Craft::$app->getSession()->setNotice('Redemption token created successfully!');
                // Use template-based redemption page instead of action
                return $this->renderTemplate('business/redeem', [
                    'entry' => $business,
                    'redeemToken' => $token,
                    'redeemType' => $redeemType,
                    'monthlySpecialData' => $redeemType === 'monthlySpecial' && $monthData ? [
                        'month' => explode('|', $monthData)[0] ?? '',
                        'special' => explode('|', $monthData)[1] ?? ''
                    ] : null,
                    'error' => null
                ]);
            } else {
                Craft::$app->getSession()->setError('Failed to create redemption token');
                return $this->redirectToPostedUrl();
            }

        } catch (\Exception $e) {
            Craft::$app->getSession()->setError('Error: ' . $e->getMessage());
            return $this->redirectToPostedUrl();
        }
    }

    /**
     * Show the redemption page
     *
     * @param string $token
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionShowRedemption(): Response
    {
        $this->requireLogin();

        $request = Craft::$app->getRequest();
        $token = $request->getParam('token');

        if (!$token) {
            return $this->renderTemplate('business/redeem', [
                'entry' => null,
                'redeemToken' => null,
                'redeemType' => null,
                'monthlySpecialData' => null,
                'error' => 'No redemption token provided'
            ]);
        }

        // Find the token using direct database query
        $tokenData = Craft::$app->db->createCommand(
            'SELECT * FROM {{%redeem_tokens}} WHERE token = :token'
        )->bindParam(':token', $token)->queryOne();

        if (!$tokenData) {
            return $this->renderTemplate('business/redeem', [
                'entry' => null,
                'redeemToken' => null,
                'redeemType' => null,
                'monthlySpecialData' => null,
                'error' => 'Redemption token not found'
            ]);
        }

        // Check if token belongs to current user
        if ($tokenData['userId'] != Craft::$app->getUser()->getId()) {
            return $this->renderTemplate('business/redeem', [
                'entry' => null,
                'redeemToken' => null,
                'redeemType' => null,
                'monthlySpecialData' => null,
                'error' => 'Unauthorized access to redemption token'
            ]);
        }

        // Check if token is expired or used
        if ($tokenData['expiresAt'] <= date('Y-m-d H:i:s') || $tokenData['usedAt']) {
            $errorMessage = $tokenData['expiresAt'] <= date('Y-m-d H:i:s')
                ? 'This redemption link has expired'
                : 'This redemption link has already been used';

            return $this->renderTemplate('business/redeem', [
                'entry' => null,
                'redeemToken' => null,
                'redeemType' => null,
                'monthlySpecialData' => null,
                'error' => $errorMessage
            ]);
        }

        // Get the business entry
        $business = Entry::find()->id($tokenData['businessId'])->one();
        if (!$business) {
            return $this->renderTemplate('business/redeem', [
                'entry' => null,
                'redeemToken' => null,
                'redeemType' => null,
                'monthlySpecialData' => null,
                'error' => 'Associated business not found'
            ]);
        }

        // Prepare template variables
        $variables = [
            'entry' => $business,
            'redeemToken' => $token,
            'redeemType' => $tokenData['redeemType'],
            'monthlySpecialData' => null,
            'tokenRecord' => (object) $tokenData,
            'error' => null
        ];

        // Handle monthly special data
        if ($tokenData['redeemType'] === 'monthlySpecial' && $tokenData['monthData']) {
            $monthData = explode('|', $tokenData['monthData'], 2);
            $variables['monthlySpecialData'] = [
                'month' => $monthData[0] ?? '',
                'special' => $monthData[1] ?? ''
            ];
        }

        return $this->renderTemplate('business/redeem', $variables);
    }

    /**
     * Mark a token as used (optional - for businesses to call when redeeming)
     *
     * @return Response
     */
    public function actionMarkAsUsed(): Response
    {
        $this->requireLogin();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $token = $request->getRequiredBodyParam('token');

        // Find the token
        $tokenRecord = RedeemTokenRecord::find()
            ->where(['token' => $token])
            ->one();

        if (!$tokenRecord) {
            throw new NotFoundHttpException('Token not found');
        }

        // Verify ownership (either the user who created it or business owner)
        $currentUserId = Craft::$app->getUser()->getId();
        if ($tokenRecord->userId !== $currentUserId) {
            // Could add business owner verification here if needed
            throw new NotFoundHttpException('Unauthorized');
        }

        if ($tokenRecord->markAsUsed()) {
            Craft::$app->getSession()->setNotice('Redemption token marked as used');
        } else {
            Craft::$app->getSession()->setError('Failed to mark token as used');
        }

        return $this->redirect("/business/redeem/{$token}");
    }

    /**
     * List user's redemption history (optional feature)
     *
     * @return Response
     */
    public function actionHistory(): Response
    {
        $this->requireLogin();

        // Get tokens using direct database query
        $tokensData = Craft::$app->db->createCommand(
            'SELECT * FROM {{%redeem_tokens}} WHERE userId = :userId ORDER BY dateCreated DESC'
        )->bindParam(':userId', Craft::$app->getUser()->getId())->queryAll();

        // Convert to objects and add business data
        $tokens = [];
        foreach ($tokensData as $tokenData) {
            $token = (object) $tokenData;
            $token->business = Entry::find()->id($tokenData['businessId'])->one();
            $token->isExpired = function() use ($tokenData) {
                return strtotime($tokenData['expiresAt']) <= time();
            };
            $token->isUsed = function() use ($tokenData) {
                return $tokenData['usedAt'] !== null;
            };
            $token->isValid = function() use ($token) {
                return !$token->isExpired() && !$token->isUsed();
            };
            $tokens[] = $token;
        }

        return $this->renderTemplate('business/redeem-history', [
            'tokens' => $tokens
        ]);
    }
}
