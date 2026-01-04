<?php

namespace modules\redeem\controllers;

use Craft;
use craft\elements\Entry;
use craft\helpers\StringHelper;
use craft\web\Controller;
use modules\redeem\records\RedeemTokenRecord;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Default controller for redeem functionality
 */
class DefaultController extends Controller
{
    /**
     * @var array
     */
    protected array|bool|int $allowAnonymous = ['test', 'qr-code', 'validate', 'test-qr'];

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
            // Set expiration far in future - tokens only expire when used, not by time
            $expiresAt = date('Y-m-d H:i:s', time() + (365 * 24 * 60 * 60 * 10)); // 10 years

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

        // Check if token is used (anyone can view/use it)
        if ($tokenData['usedAt']) {
            return $this->renderTemplate('business/redeem', [
                'entry' => null,
                'redeemToken' => null,
                'redeemType' => null,
                'monthlySpecialData' => null,
                'error' => 'This redemption code has already been used'
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
        $userId = Craft::$app->getUser()->getId();
        $tokensData = Craft::$app->db->createCommand(
            'SELECT * FROM {{%redeem_tokens}} WHERE userId = :userId ORDER BY dateCreated DESC'
        )->bindParam(':userId', $userId)->queryAll();

        // Convert to objects and add business data
        $tokens = [];
        foreach ($tokensData as $tokenData) {
            $token = (object) $tokenData;
            $token->business = Entry::find()->id($tokenData['businessId'])->one();

            // Add simple properties instead of functions
            $token->isExpired = false; // Tokens don't expire by time, only when used
            $token->isUsed = $tokenData['usedAt'] !== null;
            $token->isValid = !$token->isUsed;

            $tokens[] = $token;
        }

        return $this->renderTemplate('business/redeem-history', [
            'tokens' => $tokens
        ]);
    }

    /**
     * Test QR code generation
     *
     * @return Response
     */
    public function actionTestQr(): Response
    {
        try {
            // Simple test QR code
            $result = Builder::create()
                ->writer(new PngWriter())
                ->data('https://example.com/test')
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(ErrorCorrectionLevel::High)
                ->size(300)
                ->margin(10)
                ->build();

            $response = Craft::$app->getResponse();
            $response->format = Response::FORMAT_RAW;
            $response->headers->set('Content-Type', 'image/png');
            $response->data = $result->getString();
            return $response;
        } catch (\Exception $e) {
            return $this->asJson([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Generate QR code for a redemption token
     *
     * @return Response
     */
    public function actionQrCode(): Response
    {
        $request = Craft::$app->getRequest();
        $token = $request->getParam('token');

        if (!$token) {
            throw new NotFoundHttpException('Token parameter required');
        }

        // Verify token exists
        $tokenData = Craft::$app->db->createCommand(
            'SELECT * FROM {{%redeem_tokens}} WHERE token = :token'
        )->bindParam(':token', $token)->queryOne();

        if (!$tokenData) {
            throw new NotFoundHttpException('Invalid token');
        }

        // Generate validation URL that includes the token
        $validationUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl() . 'redeem/validate?token=' . $token;

        try {
            // Generate QR code
            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($validationUrl)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(ErrorCorrectionLevel::High)
                ->size(300)
                ->margin(10)
                ->build();

            // Return QR code as PNG image
            $response = Craft::$app->getResponse();
            $response->format = Response::FORMAT_RAW;
            $response->headers->set('Content-Type', 'image/png');
            $response->data = $result->getString();
            return $response;
        } catch (\Exception $e) {
            Craft::error('QR Code generation failed: ' . $e->getMessage(), __METHOD__);
            throw new NotFoundHttpException('QR code generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Validate and mark a QR code as used (automatically marks as redeemed on first access)
     *
     * @return Response
     */
    public function actionValidate(): Response
    {
        $request = Craft::$app->getRequest();
        $token = $request->getParam('token');

        if (!$token) {
            return $this->renderTemplate('business/redeem-validate', [
                'success' => false,
                'error' => 'No token provided',
                'token' => null
            ]);
        }

        // Find the token
        $tokenData = Craft::$app->db->createCommand(
            'SELECT * FROM {{%redeem_tokens}} WHERE token = :token'
        )->bindParam(':token', $token)->queryOne();

        if (!$tokenData) {
            return $this->renderTemplate('business/redeem-validate', [
                'success' => false,
                'error' => 'Invalid redemption code',
                'token' => null
            ]);
        }

        // Get business and user info
        $business = Entry::find()->id($tokenData['businessId'])->one();
        $user = Craft::$app->getUsers()->getUserById($tokenData['userId']);

        // Check if already used
        if ($tokenData['usedAt']) {
            return $this->renderTemplate('business/redeem-validate', [
                'success' => false,
                'error' => 'This code has already been redeemed',
                'token' => (object) $tokenData,
                'business' => $business,
                'user' => $user,
                'alreadyUsed' => true
            ]);
        }

        // Mark as used immediately (first scan = redemption)
        $now = date('Y-m-d H:i:s');
        Craft::$app->db->createCommand()
            ->update(
                '{{%redeem_tokens}}',
                ['usedAt' => $now, 'dateUpdated' => $now],
                ['token' => $token, 'usedAt' => null] // Only update if not already used
            )
            ->execute();

        // Show success page
        return $this->renderTemplate('business/redeem-validate', [
            'success' => true,
            'error' => null,
            'token' => (object) array_merge($tokenData, ['usedAt' => $now]),
            'business' => $business,
            'user' => $user,
            'justRedeemed' => true
        ]);
    }

    /**
     * Admin page to manage user redemptions
     *
     * @return Response
     */
    public function actionAdminRedemptions(): Response
    {
        $this->requireAdmin();

        // Get all users with redemption counts
        $users = Craft::$app->getUsers()->getAllUsers();
        $userData = [];

        foreach ($users as $user) {
            $totalTokens = Craft::$app->db->createCommand(
                'SELECT COUNT(*) FROM {{%redeem_tokens}} WHERE userId = :userId'
            )->bindValue(':userId', $user->id)->queryScalar();

            $usedTokens = Craft::$app->db->createCommand(
                'SELECT COUNT(*) FROM {{%redeem_tokens}} WHERE userId = :userId AND usedAt IS NOT NULL'
            )->bindValue(':userId', $user->id)->queryScalar();

            $activeTokens = Craft::$app->db->createCommand(
                'SELECT COUNT(*) FROM {{%redeem_tokens}} WHERE userId = :userId AND usedAt IS NULL'
            )->bindValue(':userId', $user->id)->queryScalar();

            if ($totalTokens > 0) {
                $userData[] = [
                    'user' => $user,
                    'total' => $totalTokens,
                    'used' => $usedTokens,
                    'active' => $activeTokens
                ];
            }
        }

        return $this->renderTemplate('admin/redemptions', [
            'userData' => $userData
        ]);
    }

    /**
     * Clear redemption history for a specific user
     *
     * @return Response
     */
    public function actionClearUserHistory(): Response
    {
        $this->requireAdmin();
        $this->requirePostRequest();

        $userId = Craft::$app->getRequest()->getRequiredBodyParam('userId');
        $user = Craft::$app->getUsers()->getUserById($userId);

        if (!$user) {
            Craft::$app->getSession()->setError('User not found');
            return $this->redirect('/admin/redemptions');
        }

        // Delete all redemption tokens for this user
        $deleted = Craft::$app->db->createCommand()
            ->delete('{{%redeem_tokens}}', ['userId' => $userId])
            ->execute();

        Craft::$app->getSession()->setNotice("Cleared {$deleted} redemption(s) for {$user->getName()}");
        return $this->redirect('/admin/redemptions');
    }
}
