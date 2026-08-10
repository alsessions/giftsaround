<?php

namespace modules\registration\controllers;

use Craft;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\Db;
use craft\web\Controller;
use Solspace\Freeform\Elements\Submission;
use Throwable;
use yii\web\Response;

class DefaultController extends Controller
{
    protected array|bool|int $allowAnonymous = ['complete'];

    public function actionComplete(): Response
    {
        $session = Craft::$app->getSession();
        $request = Craft::$app->getRequest();
        $paymentIntentId = $this->resolvePaymentIntentId();
        $paymentRecord = $paymentIntentId ? $this->getPaymentRecord($paymentIntentId) : null;
        $stripePaymentIntent = null;
        $stripeLookupError = null;
        $needsPassword = false;

        if ($paymentIntentId && !$this->paymentRecordIsValid($paymentRecord)) {
            try {
                $stripePaymentIntent = $this->getStripePaymentIntent($paymentIntentId, ['customer']);
            } catch (Throwable $e) {
                $stripeLookupError = $e->getMessage();
                Craft::warning($e->getMessage(), __METHOD__);
            }
        }

        if ($paymentIntentId && ($this->paymentRecordIsValid($paymentRecord) || $this->stripePaymentIntentIsValid($stripePaymentIntent))) {
            if ($this->paymentIsConsumed($paymentIntentId)) {
                $session->setNotice('This payment has already been used to create an account.');
                return $this->redirect(Craft::$app->getUser()->getIdentity() ? '/account' : '/login');
            }

            [$email, $fullName, $submissionId] = $this->getRegistrationData($paymentRecord, $stripePaymentIntent);
            $password = (string)$request->getBodyParam('password', '');
            $passwordConfirm = (string)$request->getBodyParam('passwordConfirm', '');

            if (!$email) {
                $session->setError('We could not find an email address for this paid registration.');
            } elseif (!$request->getIsPost() || !$password || $password !== $passwordConfirm) {
                $needsPassword = true;
                if ($request->getIsPost()) {
                    $session->setError('Please enter matching passwords.');
                }
            } else {
                $user = $this->createPaidUser($paymentIntentId, $email, $fullName, $password, $submissionId);

                if ($user) {
                    Craft::$app->getUser()->login($user);
                    $session->setSuccess('Your account has been created.');
                    return $this->redirect('/account');
                }
            }
        }

        return $this->renderTemplate('users/complete-registration', [
            'paymentIntentId' => $paymentIntentId,
            'paymentRecord' => $paymentRecord,
            'stripePaymentIntent' => $stripePaymentIntent,
            'stripeLookupError' => $stripeLookupError,
            'stripeRedirectStatus' => Craft::$app->getRequest()->getParam('redirect_status'),
            'expectedPaymentAmount' => $this->getExpectedPaymentAmount(),
            'needsPassword' => $needsPassword,
        ]);
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
            $submission = $submissionToken ? Submission::find()->token($submissionToken)->one() : null;

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

    private function stripePaymentIntentIsValid(?object $paymentIntent): bool
    {
        return $paymentIntent
            && ($paymentIntent->status ?? null) === 'succeeded'
            && (int)($paymentIntent->amount ?? 0) >= $this->getExpectedPaymentAmount()
            && strtolower((string)($paymentIntent->currency ?? '')) === 'usd';
    }

    private function getRegistrationData(?array $paymentRecord, ?object $stripePaymentIntent): array
    {
        $submission = ($paymentRecord && $paymentRecord['submissionId'])
            ? Submission::find()->id($paymentRecord['submissionId'])->one()
            : null;

        $values = $submission ? $submission->getFormFieldValues() : [];
        $customer = $stripePaymentIntent->customer ?? null;

        $email = trim((string)($values['email'] ?? $customer->email ?? $stripePaymentIntent->receipt_email ?? ''));
        $fullName = trim((string)($values['name'] ?? $customer->name ?? ''));

        return [$email, $fullName, $submission?->id];
    }

    private function createPaidUser(string $paymentIntentId, string $email, ?string $fullName, string $password, ?int $submissionId): ?User
    {
        $session = Craft::$app->getSession();
        $existingUser = Craft::$app->getUsers()->getUserByUsernameOrEmail($email);

        if ($existingUser) {
            $session->setError('An account already exists for this email address. Please sign in.');
            return null;
        }

        $user = new User();
        $user->username = $this->generateUsername($email);
        $user->email = $email;
        $user->fullName = $fullName ?: null;
        $user->newPassword = $password;
        $user->pending = false;
        $user->active = true;
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
                return null;
            }

            Craft::$app->getUsers()->activateUser($user);

            if ($groups) {
                Craft::$app->getUsers()->assignUserToDefaultGroup($user);
            }

            $this->consumePayment($paymentIntentId, $user->id);
            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            Craft::error($e->getMessage(), __METHOD__);
            $session->setError('We could not create your account. Please contact support.');
            return null;
        }

        return $user;
    }

    private function generateUsername(string $email): string
    {
        $base = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', strstr($email, '@', true) ?: 'user'));
        $base = trim($base, '-') ?: 'user';
        $username = $base;
        $counter = 2;

        while (Craft::$app->getUsers()->getUserByUsernameOrEmail($username)) {
            $username = $base.'-'.$counter;
            $counter++;
        }

        return $username;
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
