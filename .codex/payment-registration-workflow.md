# Payment-Gated User Registration Workflow

## Problem

The `userRegistration` Freeform form was creating Craft users before Stripe payment was confirmed. If a card was declined or had insufficient funds, a pending/unactivated user could still be created.

The desired behavior is:

- No Craft user should be created before successful payment.
- Declined payments should not create users.
- The customer should enter their password before payment, confirm it, then land in their account after successful payment.

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
5. If payment is successful, the registration controller creates the Craft user automatically.
6. The controller rejects already-consumed PaymentIntent IDs, creates an active Craft user with the submitted password, assigns the default `Users` group, logs the user in, marks the PaymentIntent as consumed, and redirects to `/account`.

## Template Routes

The Craft routes needed are:

```php
'register/complete' => 'registration/default/complete',
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
- Amount is at least the configured Freeform Stripe field amount.
- Currency is `usd`.

If successful, it uses Freeform submission data first, then Stripe customer data as fallback, for:

- `email`
- `fullName`
- `password`
- `passwordConfirm`

Then it creates the user immediately, activates them, logs them in, clears the stored encrypted password fields from the Freeform submission, and redirects to `/account`.

The final save stores consumed PaymentIntent IDs in:

```text
paid_registration_payments
```

This prevents a successful payment link from creating more than one account.

## Freeform Form Changes

For the final workflow, the Freeform User element integration should be disabled or removed from the `userRegistration` form.

The payment form should collect contact/customer data, password, password confirmation, and payment. Username should not be collected. The controller generates a unique internal username from the email address.

Password fields are encrypted Freeform text fields and are cleared from the submission after account creation. The `/register/complete` page should not collect username/password.

## Redemption Impact

The redemption process should continue to work as long as the new Craft user is assigned to the default `Users` group.

Current project config shows public registration is enabled and the default group is `Users`:

```yaml
allowPublicRegistration: true
defaultGroup: f9b21139-b37c-4768-95ce-6a52e55e6352 # Users
requireEmailVerification: true
```

The custom registration controller assigns the default group and activates the user directly, bypassing the email activation requirement for paid registrations.

## Operational Notes

Avoid making direct SQL changes on production where possible.

Preferred production process:

1. Deploy template/route changes.
2. In the Freeform control panel, disable the User integration for `userRegistration`.
3. Set the Stripe field success/failed redirects shown above.
4. Run Craft migrations so the consumed-payment table exists and the local Freeform return URL cleanup is applied.
5. Test successful payment with a Stripe test card.
6. Confirm the payment form includes password and confirm password fields before Stripe.
7. Confirm mismatched passwords are blocked before payment submission.
8. Confirm the `/register/complete` redirect creates an active Craft user automatically.
9. Confirm the user is assigned to the default `Users` group and is logged in at `/account`.
10. Revisit the same completion URL and confirm it cannot create a second account.
11. Test a declined card and confirm no Craft user is created.

## Known Edge Case

If a customer successfully pays but the completion redirect fails before account creation, there may be a successful Stripe payment without a Craft user account.

Possible follow-up solutions:

- Add an admin recovery workflow for paid submissions without matching users.
- Revisit `/register/complete?paymentIntent=pi_...` after the issue is fixed.
