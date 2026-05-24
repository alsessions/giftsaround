# Payment-Gated User Registration Workflow

## Problem

The `userRegistration` Freeform form was creating Craft users before Stripe payment was confirmed. If a card was declined or had insufficient funds, a pending/unactivated user could still be created.

The desired behavior is:

- No Craft user should be created before successful payment.
- Declined payments should not create users.
- After a successful payment, the customer should enter their username/password and complete Craft-native registration.

## Findings

The current Freeform setup combines:

- A Stripe payment field.
- A Freeform User element integration.
- Craft user activation email behavior.

Freeform's Stripe callback can process the saved form after Stripe returns, but the User element integration is separate from payment success handling. Freeform's "Suppress Email Notifications & Integrations when Payments Fail" setting does not reliably stop the User element integration from creating users.

The safer native workflow is to separate payment collection from account creation.

## Target Workflow

1. Customer submits the Freeform payment form.
2. Stripe processes the payment.
3. On successful payment, Stripe redirects to:

   ```text
   /register/complete?paymentIntent={{ paymentIntent.id }}
   ```

4. The completion page verifies the payment.
5. If payment is successful, the page displays a Craft-native registration form with username/password fields.
6. The form posts to Craft's native action:

   ```twig
   {{ actionInput('users/save-user') }}
   ```

7. Craft creates a pending user, assigns the default `Users` group, and sends the email verification/activation email.

## Template Routes

The Craft routes needed are:

```php
'register/complete' => ['template' => 'users/complete-registration'],
'register/payment-failed' => ['template' => 'users/payment-failed'],
```

These belong in:

```text
config/routes.php
```

## Freeform Stripe Redirects

These should be set on the **Stripe field** in the Freeform form layout, not only in the form-level "Success & Errors" settings.

Successful Payment Redirect:

```text
/register/complete?paymentIntent={{ paymentIntent.id }}
```

Failed Payment Redirect:

```text
/register/payment-failed?paymentIntent={{ paymentIntent.id }}
```

Earlier redirect attempts used `submission.id` and `submission.token`, but production returned URLs like:

```text
/register/complete?submissionId=&submission=<token>
```

The empty `submissionId` meant the completion page could not reliably find the Freeform submission. Production also failed to resolve the submission by token, so the PaymentIntent ID became the more reliable lookup key.

## Completion Page Behavior

The completion page accepts:

```text
paymentIntent=pi_...
```

It attempts, in order:

1. Resolve a Freeform submission/payment record from local Freeform data.
2. If that fails, verify the PaymentIntent directly through the configured Freeform Stripe integration.

The direct Stripe fallback checks:

- PaymentIntent status is `succeeded`.
- Amount is at least `999`.
- Currency is `usd`.

If successful, it uses Stripe customer data for:

- `email`
- `fullName`

Then it displays username/password fields and submits to Craft's native `users/save-user` action.

## Freeform Form Changes

For the final workflow, the Freeform User element integration should be disabled or removed from the `userRegistration` form.

The payment form should collect payment and contact/customer data only. Username/password should be collected on the `/register/complete` page after payment succeeds.

If the User integration remains enabled, Craft may still create users before the customer reaches the password step.

## Redemption Impact

The redemption process should continue to work as long as the new Craft user is assigned to the default `Users` group.

Current project config shows public registration is enabled and the default group is `Users`:

```yaml
allowPublicRegistration: true
defaultGroup: f9b21139-b37c-4768-95ce-6a52e55e6352 # Users
requireEmailVerification: true
```

Craft's native public registration assigns that default group automatically.

## Operational Notes

Avoid making direct SQL changes on production where possible.

Preferred production process:

1. Deploy template/route changes.
2. In the Freeform control panel, disable the User integration for `userRegistration`.
3. Set the Stripe field success/failed redirects shown above.
4. Test successful payment with a Stripe test card.
5. Confirm the `/register/complete` page shows username/password fields.
6. Submit the completion form.
7. Confirm a pending Craft user is created and receives the activation email.
8. Test a declined card and confirm no Craft user is created.

## Known Edge Case

If a customer successfully pays but closes the browser before completing the Craft registration step, there may be a successful Stripe payment without a Craft user account.

Possible follow-up solutions:

- Email the completion link after successful payment.
- Add an admin recovery workflow for paid submissions without matching users.
- Store a short-lived paid-registration token and resend it on request.
