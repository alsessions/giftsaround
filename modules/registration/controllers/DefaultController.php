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
    protected array|bool|int $allowAnonymous = ['create-user'];

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
        $user->username = $username ?: $email;
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
            if (!Craft::$app->getElements()->saveElement($user, false)) {
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
        $payment = (new Query())
            ->select(['status', 'amount', 'currency'])
            ->from('{{%freeform_payments}}')
            ->where(['resourceId' => $paymentIntentId])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return ($payment
            && ($payment['status'] ?? null) === 'succeeded'
            && (float)($payment['amount'] ?? 0) >= 999
            && strtolower((string)($payment['currency'] ?? '')) === 'usd')
            || $this->stripePaymentIsValid($paymentIntentId);
    }

    private function stripePaymentIsValid(string $paymentIntentId): bool
    {
        try {
            $freeform = Craft::$app->getPlugins()->getPlugin('freeform');
            $integrations = $freeform?->integrations;
            $integrationModel = $integrations?->getByHandle('stripe')
                ?? $integrations?->getByHandle('Stripe');
            $integration = $integrationModel?->getIntegrationObject();

            if (!$integration) {
                return false;
            }

            $paymentIntent = $integration->getStripeClient()->paymentIntents->retrieve($paymentIntentId);

            return $paymentIntent->status === 'succeeded'
                && (int)$paymentIntent->amount >= 999
                && strtolower((string)$paymentIntent->currency) === 'usd';
        } catch (Throwable $e) {
            Craft::warning($e->getMessage(), __METHOD__);
            return false;
        }
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
