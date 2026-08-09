<?php

namespace modules\registration\controllers;

use Craft;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use Throwable;
use yii\web\Response;

class DefaultController extends Controller
{
    protected array|bool|int $allowAnonymous = ['complete', 'create-user'];

    public function actionComplete(): Response
    {
        $paymentIntentId = $this->resolvePaymentIntentId();
        $paymentRecord = $paymentIntentId ? $this->getPaymentRecord($paymentIntentId) : null;
        $stripePaymentIntent = null;
        $stripeLookupError = null;

        if ($paymentIntentId && !$this->paymentRecordIsValid($paymentRecord)) {
            try {
                $stripePaymentIntent = $this->getStripePaymentIntent($paymentIntentId, ['customer']);
            } catch (Throwable $e) {
                $stripeLookupError = $e->getMessage();
                Craft::warning($e->getMessage(), __METHOD__);
            }
        }

        return $this->renderTemplate('users/complete-registration', [
            'paymentIntentId' => $paymentIntentId,
            'paymentRecord' => $paymentRecord,
            'stripePaymentIntent' => $stripePaymentIntent,
            'stripeLookupError' => $stripeLookupError,
            'stripeRedirectStatus' => Craft::$app->getRequest()->getParam('redirect_status'),
            'expectedPaymentAmount' => $this->getExpectedPaymentAmount(),
        ]);
    }

    public function actionCreateUser(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();
        $token = $request->getBodyParam('paidRegistrationToken');
        $payload = $this->decodePayload($token);

        if (!$payload) {
            $session->setError('Your registration session expired. Please return to registration and try again.');
            return $this->redirect('/register');
        }

        $paymentIntentId = $payload['paymentIntent'] ?? null;
        $email = trim((string)($payload['email'] ?? $request->getBodyParam('email', '')));
        $fullName = trim((string)($payload['fullName'] ?? ''));

        if (!$paymentIntentId || !$email || !$this->paymentIsValid($paymentIntentId)) {
            $session->setError('We could not verify a completed payment for this registration.');
            return $this->redirect('/register');
        }

        if ($this->paymentIsConsumed($paymentIntentId)) {
            $session->setError('This payment has already been used to create an account.');
            return $this->redirect('/login');
        }

        $username = trim((string)$request->getBodyParam('username'));
        $password = (string)$request->getBodyParam('password');

        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->fullName = $fullName ?: null;
        $user->newPassword = $password;
        $user->pending = true;
        $user->affiliatedSiteId = Craft::$app->getSites()->getCurrentSite()->id;
        $user->setScenario(User::SCENARIO_REGISTRATION);

        $groups = Craft::$app->getUsers()->getDefaultUserGroups($user);
        if ($groups) {
            $user->setGroups($groups);
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            if (!Craft::$app->getElements()->saveElement($user)) {
                $transaction->rollBack();
                $this->flashUserErrors($user);
                return $this->redirect(UrlHelper::url('register/complete', ['paymentIntent' => $paymentIntentId]));
            }

            if ($groups) {
                Craft::$app->getUsers()->assignUserToDefaultGroup($user);
            }

            $this->consumePayment($paymentIntentId, $user->id);
            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            Craft::error($e->getMessage(), __METHOD__);
            $session->setError('We could not create your account. Please try again.');
            return $this->redirect(UrlHelper::url('register/complete', ['paymentIntent' => $paymentIntentId]));
        }

        try {
            Craft::$app->getUsers()->sendActivationEmail($user);
        } catch (Throwable $e) {
            Craft::warning($e->getMessage(), __METHOD__);
        }

        $session->setSuccess('User registered.');
        return $this->redirect('/register/success');
    }

    private function decodePayload(?string $token): ?array
    {
        if (!$token) {
            return null;
        }

        $json = Craft::$app->getSecurity()->validateData($token);
        if ($json === false) {
            return null;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload) || (int)($payload['expires'] ?? 0) < time()) {
            return null;
        }

        return $payload;
    }

    private function paymentIsValid(string $paymentIntentId): bool
    {
        $payment = $this->getPaymentRecord($paymentIntentId);

        return $this->paymentRecordIsValid($payment) || $this->stripePaymentIsValid($paymentIntentId);
    }

    private function stripePaymentIsValid(string $paymentIntentId): bool
    {
        try {
            $paymentIntent = $this->getStripePaymentIntent($paymentIntentId);

            return $paymentIntent->status === 'succeeded'
                && (int)$paymentIntent->amount >= $this->getExpectedPaymentAmount()
                && strtolower((string)$paymentIntent->currency) === 'usd';
        } catch (Throwable $e) {
            Craft::warning($e->getMessage(), __METHOD__);
            return false;
        }
    }

    private function resolvePaymentIntentId(): ?string
    {
        $request = Craft::$app->getRequest();
        $paymentIntentId = $request->getParam('paymentIntent') ?: $request->getParam('payment_intent');
        $paymentIntentClientSecret = $request->getParam('payment_intent_client_secret');

        if (!$paymentIntentId && $paymentIntentClientSecret && str_contains($paymentIntentClientSecret, '_secret_')) {
            $paymentIntentId = explode('_secret_', $paymentIntentClientSecret)[0];
        }

        if (!$paymentIntentId) {
            $submissionToken = $request->getParam('submissionToken') ?: $request->getParam('submission');
            $submission = $submissionToken ? \Solspace\Freeform\Elements\Submission::find()->token($submissionToken)->one() : null;

            if ($submission) {
                $paymentIntentId = (new Query())
                    ->select(['resourceId'])
                    ->from('{{%freeform_payments}}')
                    ->where(['submissionId' => $submission->id])
                    ->orderBy(['id' => SORT_DESC])
                    ->scalar();
            }
        }

        return $paymentIntentId ?: null;
    }

    private function getPaymentRecord(string $paymentIntentId): ?array
    {
        $payment = (new Query())
            ->select(['submissionId', 'status', 'amount', 'currency'])
            ->from('{{%freeform_payments}}')
            ->where(['resourceId' => $paymentIntentId])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $payment ?: null;
    }

    private function paymentRecordIsValid(?array $payment): bool
    {
        return $payment
            && ($payment['status'] ?? null) === 'succeeded'
            && (float)($payment['amount'] ?? 0) >= $this->getExpectedPaymentAmount()
            && strtolower((string)($payment['currency'] ?? '')) === 'usd';
    }

    private function getExpectedPaymentAmount(): int
    {
        $amount = (new Query())
            ->select(["JSON_UNQUOTE(JSON_EXTRACT([[ff.metadata]], '$.amount'))"])
            ->from(['ff' => '{{%freeform_forms_fields}}'])
            ->innerJoin(['f' => '{{%freeform_forms}}'], '[[f.id]] = [[ff.formId]]')
            ->where(['f.handle' => 'userRegistration'])
            ->andWhere(['like', 'ff.type', 'StripeField'])
            ->orderBy(['ff.id' => SORT_ASC])
            ->scalar();

        return is_numeric($amount) ? (int)round((float)$amount * 100) : 999;
    }

    private function getStripePaymentIntent(string $paymentIntentId, array $expand = []): object
    {
        $integration = $this->getStripeIntegration();

        if (!$integration || !method_exists($integration, 'getStripeClient')) {
            throw new \RuntimeException('No Stripe integration could be resolved for paid registration.');
        }

        $options = $expand ? ['expand' => $expand] : [];

        return $integration->getStripeClient()->paymentIntents->retrieve($paymentIntentId, $options);
    }

    private function getStripeIntegration(): ?object
    {
        $freeform = Craft::$app->getPlugins()->getPlugin('freeform');
        $integrations = $freeform?->integrations;

        $integrationUid = (new Query())
            ->select(["JSON_UNQUOTE(JSON_EXTRACT([[ff.metadata]], '$.integration'))"])
            ->from(['ff' => '{{%freeform_forms_fields}}'])
            ->innerJoin(['f' => '{{%freeform_forms}}'], '[[f.id]] = [[ff.formId]]')
            ->where(['f.handle' => 'userRegistration'])
            ->andWhere(['like', 'ff.type', 'StripeField'])
            ->orderBy(['ff.id' => SORT_ASC])
            ->scalar();

        $integrationModel = $integrationUid ? $integrations?->getByUid($integrationUid) : null;
        $integrationModel = $integrationModel
            ?? $integrations?->getByHandle('stripe')
            ?? $integrations?->getByHandle('Stripe');

        return $integrationModel?->getIntegrationObject();
    }

    private function paymentIsConsumed(string $paymentIntentId): bool
    {
        return (new Query())
            ->from('{{%paid_registration_payments}}')
            ->where(['paymentIntent' => $paymentIntentId])
            ->exists();
    }

    private function consumePayment(string $paymentIntentId, int $userId): void
    {
        Craft::$app->getDb()->createCommand()->insert('{{%paid_registration_payments}}', [
            'paymentIntent' => $paymentIntentId,
            'userId' => $userId,
            'dateCreated' => Db::prepareDateForDb(new \DateTime()),
            'dateUpdated' => Db::prepareDateForDb(new \DateTime()),
            'uid' => Craft::$app->getSecurity()->generateRandomString(32),
        ])->execute();
    }

    private function flashUserErrors(User $user): void
    {
        $errors = [];

        foreach ($user->getErrors() as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $errors[] = $error;
            }
        }

        Craft::$app->getSession()->setError($errors ? implode(' ', $errors) : 'Could not create your account.');
    }
}
