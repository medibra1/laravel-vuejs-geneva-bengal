# Payments — Stripe deposits (card + TWINT)

The most-iterated part of this codebase — the flow below is the result of
several real bugs found in manual testing (see `CLAUDE.md` for the full
incident history if you need the "why did it used to work differently"
context). This document describes the **current** shape only.

## Why capture is manual, and why TWINT is the exception

A deposit reserves a kitten. Two visitors could otherwise both complete a
payment for the same cat before either one is durably recorded. The fix:
card payments authorize now, capture later — `capture_method: manual` on
Stripe — so a losing authorization can be released without ever charging
the client, once the app has determined who actually gets the cat.

**TWINT does not support manual capture at all** (confirmed against a real
Stripe test-mode PaymentIntent — `capture_method=manual is not supported
by payment method type twint`). TWINT settles the instant the client
confirms in the app. So `payment_method_options.card.capture_method =
manual` is set only for card; TWINT stays on its only supported mode
(automatic). This is why `PaymentGateway::isCaptured()` exists as its own
method — the arbitration logic (below) needs to know, per payment method,
whether there's still an authorization to release or an actual charge to
refund.

## No hold, no `Deposit`, until payment is confirmed

Two prior designs were tried and abandoned — see `CLAUDE.md` if you want
the full story:
1. A `Deposit` row created at checkout time, `pending` — visible in the
   admin the instant a visitor typed their email, before any payment.
2. A `CheckoutHold` row (a real lock with a sliding TTL, pinged by the
   frontend) — blocked the kitten for *every* visitor from the moment one
   person merely opened the payment page, even if they never typed a card
   number.

**Current design**: nothing is written to the database when a visitor
lands on the payment page. `Public\DepositController::store()` only
renders the checkout form. The Stripe PaymentIntent itself is created
**at the "Pay" click**, not on page load
(`Public\DepositController::confirmIntent()`, `POST
/deposits/confirm-intent`) — this is also the only point that re-checks
`Deposit::blocksNewReservation()` before ever talking to Stripe.

`PaymentIntentTracking` (`payment_intent_tracking` table) is written right
after a PaymentIntent is successfully created — not a lock, just a trace
row so `ReconcileCheckouts` (below) has something to inspect if the
webhook never arrives. It's deleted the moment a real `Deposit` is built.

**The webhook is the only creator of a `Deposit`.** No PaymentIntent
metadata round-trips through a database row before that — everything the
webhook needs (`cat_id`, `name`, `email`, `phone`, `locale`) rides along
as Stripe PaymentIntent metadata (see `CheckoutData` /
`StripeGateway::createPaymentIntent()`), since Stripe is the only place
that data lives until payment is confirmed.

## Sequence

```
Visitor                Public\DepositController      Stripe                  Webhook / Cron
  │                                                      
  ├─ GET checkout page ─▶ store()                        
  │                        renders form only,             
  │                        no PaymentIntent yet            
  │                                                      
  ├─ clicks "Pay" ──────▶ confirmIntent()                
  │                        1. Deposit::blocksNewReservation()?
  │                           → yes: reject, no Stripe call
  │                           → no: continue
  │                        2. createPaymentIntent()  ───▶ PaymentIntent created
  │                                                        (capture_method: manual
  │                                                         for card only)
  │                        3. PaymentIntentTracking row written
  │  ◀── clientSecret ──────────────────────────────────
  │                                                      
  ├─ elements.submit() + confirmPayment() ──────────────▶ card/TWINT confirmed
  │                                                        │
  │                                                        ├─ amount_capturable_updated (card)
  │                                                        │  or succeeded (TWINT, auto-captured)
  │                                                        ▼
  │                                            StripeWebhookController::handle()
  │                                              → DepositPaymentProcessor::createFromPayment()
  │                                                 - lockForUpdate() the Cat row
  │                                                 - already a Paid deposit for this cat?
  │                                                    → yes: loseRace() — refund (TWINT,
  │                                                      already captured) or cancelAuthorization()
  │                                                      (card, still just authorized)
  │                                                    → no: build the Deposit, capture() if
  │                                                      needed, cat → en_attente, delete
  │                                                      the tracking row, send confirmations
  │                                                      (client first, then staff)
  │
  ├─ redirected to deposits/return/{paymentIntentId} (DepositReturn.vue polls until resolved)
```

`DepositPaymentProcessor::createFromPayment()` is the **sole arbitration
point** between two visitors who both confirm payment for the same cat —
nothing upstream blocks that anymore. It's expected to stay rare in
practice (two people would need to both finish paying before either
webhook lands) but is fully handled, not just theoretically possible.

## `ReconcileCheckouts` — the safety net

Scheduled every 15 minutes (see [architecture.md](architecture.md#the-cronrun-endpoint--why-it-exists)
for why this runs via `/cron/run` rather than a real cron/worker). Two
unrelated jobs in one:

1. **Stale `PaymentIntentTracking` rows** — a PaymentIntent was created but
   neither the webhook nor a previous run has resolved it. `GRACE_PERIOD_MINUTES`
   (`15` in `app/Jobs/ReconcileCheckouts.php` as of this writing — it has
   been tuned more than once in response to real testing, check that file
   for the current value if precision matters) exists to tell "webhook
   genuinely lost" apart from "visitor still mid-checkout".
   Past that grace period, the job calls `PaymentGateway::retrieveCheckoutData()`
   directly against Stripe and routes the result through the exact same
   `createFromPayment()` used by the webhook — no separate win/lose logic
   duplicated here.
2. **Paid `Deposit` rows whose confirmation email never sent** —
   production runs `QUEUE_CONNECTION=sync` (no daemon worker on the
   target shared hosting, see [../DEPLOY.md](../DEPLOY.md) §1/§2), so a
   failed SMTP send has no queue-level retry. This loop is the only thing
   that retries it, up to `CONFIRMATION_MAX_ATTEMPTS` (5) before notifying
   staff to reach the client directly instead.

Both loops wrap Stripe/mail calls in a per-row `try/catch` — a single bad
row (e.g. an already-refunded PaymentIntent hit again) must never abort
the whole batch and starve every other stale row queued behind it. This
was a real bug, fixed after being found in production-like testing.

## Manual (admin-recorded) reservations — a separate, simpler path

`Admin\DepositController::store()` lets staff record a reservation taken
by phone/in person. Unlike the public flow, this **does** call
`DepositPaymentProcessor::reserve()` immediately — a staff-entered
reservation is trusted the moment it's made, no payment confirmation to
wait for. Payment method is `Cash|BankTransfer|TwintManual`, or left
`null` ("to be defined later") and resolved when marking paid
(`Admin\DepositController::markPaid()`). Stripe was deliberately removed
from this form's options (2026-08-10) — the "payment link" it used to
generate never led to a real Payment Element, just a status page.

`DepositPaymentProcessor::finalizeDirectly()` exists for adoptions handled
entirely off-system (a gift, an in-person sale with no deposit at all) —
`super_admin` only, still creates a `Deposit` (amount `0`, `provider =
'manual_no_deposit'`) purely as a traceable record, rather than silently
flipping the cat's status with nothing to show for it later.

`DepositPaymentProcessor::cancel()` undoes a **paid** deposit — releases
the cat back to `disponible` regardless of whether it was `en_attente` or
already `adopte`, and marks the deposit `cancelled`. It does **not** touch
Stripe (no refund) — that's a separate, earlier step
(`Admin\DepositController::refund()`) if the client needs their money
back, since `refund()` itself requires `status === Paid`, a state
`cancel()` removes.

## `PaymentGateway` interface

```php
interface PaymentGateway
{
    public function createPaymentIntent(CheckoutData $checkoutData): PaymentIntentResult;
    public function handleWebhook(Request $request): PaymentWebhookResult;
    public function retrieveCheckoutData(string $paymentIntentId): PaymentWebhookResult;
    public function capture(Deposit $deposit): bool;
    public function cancelAuthorization(Deposit $deposit): bool;
    public function refund(Deposit $deposit): bool;
    public function isCheckoutPaid(Deposit $deposit): bool;
    public function isCaptured(Deposit $deposit): bool;
}
```

Kept PSP-agnostic on purpose (`StripeGateway` is the only implementation
today) — `app/Services/Payments/`. `createPaymentIntent()` takes
`CheckoutData`, not a `Deposit`, since no `Deposit` exists yet when it's
called.

## Frontend (`Public/DepositPay.vue`)

Stripe.js Payment Element mounts in **deferred mode**
(`elements({ mode: 'payment', amount, currency, paymentMethodTypes: ['card', 'twint'] })`
— no `clientSecret` yet, since no PaymentIntent exists until "Pay" is
clicked). `paymentMethodTypes` must stay in sync with what
`StripeGateway::createPaymentIntent()` sends server-side, or Stripe falls
back to the account's other default methods (this broke TWINT visibility
once — see `CLAUDE.md`).

At submit: `elements.submit()` **must** be called before `confirmIntent()`
and before `stripe.confirmPayment()` — Stripe requires this explicit call,
synchronously at the click, before any async work; skipping it throws
`IntegrationError`. Then `POST /deposits/confirm-intent` → on success,
`stripe.confirmPayment({ elements, clientSecret, confirmParams: {
return_url }, redirect: 'if_required' })`. TWINT and 3D Secure always force
a real browser redirect regardless of `redirect: 'if_required'`; a card
needing no extra authentication resolves in-page and the component
navigates to `deposits.return` itself.

`Public/DepositReturn.vue` polls `router.reload({ only: ['depositStatus'] })`
every 3.5s (capped at 20 attempts, ~70s) while status is `pending` — it
never trusts the browser's own belief that payment succeeded, only what
the backend actually recorded. Past the cap, a dedicated "still
processing, no action needed" message replaces the generic pending state.

## Testing

`tests/Doubles/FakePaymentGateway` — full in-memory double, tracks
`createPaymentIntentDepositIds` / `capturedDepositIds` /
`cancelledDepositIds` / `refundedDepositIds` so a test can assert not just
that an action happened but that a specific deposit was/wasn't touched.

`tests/Doubles/FakeCaptureStripeGateway` — extends the *real*
`StripeGateway` (keeps `handleWebhook()`'s signature-verification logic
genuinely exercised, since it never calls the network) while overriding
`capture()`/`cancelAuthorization()`/etc., so `StripeWebhookTest.php` can
exercise `markPaid()`'s real side effects without ever hitting the live
Stripe API.

Key scenarios covered in `tests/Feature/Public/StripeWebhookTest.php` and
`tests/Feature/ReconcileCheckoutsTest.php`: full public flow end-to-end,
TWINT paid without ever calling `capture()`, a lost race for both card
(cancelled, never charged) and TWINT (captured then refunded), webhook
idempotency (replayed event on an already-paid deposit is a no-op),
reconciliation of a stale tracking row, and confirmation-email retry.
