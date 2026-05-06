# Razorpay ePayment Integration — Athlete Event Registration

This document is the deliverable for the Razorpay integration that adds an
**Online Payment** option alongside the existing **Manual Payment** flow on
the athlete event-registration page.

The manual flow is fully untouched. Athletes who started a manual payment
can still finish it; admins still see and approve manual transactions.
Auto-approval for ePayment fires only after a successful HMAC-SHA256
signature verification.

---

## 1. Files created and modified

### Created
| File | Why |
| --- | --- |
| `app/core/Razorpay.php`                    | Tiny cURL client. `createOrder()` and `verifySignature()` only. No SDK / Composer. |
| `database/fix_razorpay_columns.sql`        | Idempotent stored-procedure SQL for the four new columns + unique index. |
| `.env.example`                             | Template `.env` checked into the repo. Lists every env var the app reads, including `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET`. |
| `docs/RAZORPAY_INTEGRATION.md`             | This document. |

### Modified
| File | Why |
| --- | --- |
| `app/config/app.php`                       | New `'razorpay'` config block, reads `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` from env. |
| `app/models/Schema.php`                    | `ensureRegistrationFlow()` now also adds the four Razorpay columns + `uq_rzp_order` unique index on `event_registration_payments`. Auto-applied on first request after deploy. |
| `app/models/EventRegistrationPayment.php`  | New `findByOrderId(string)` helper used by `payVerify`. |
| `app/controllers/AthleteController.php`    | Imports `Core\Razorpay`. New actions `payCreateOrder` and `payVerify`. |
| `app/public/index.php`                     | Two new POST routes: `/athlete/events/{id}/pay/create-order` and `/athlete/events/{id}/pay/verify`. |
| `app/views/layouts/app.php`                | Conditional `<script src="https://checkout.razorpay.com/v1/checkout.js" defer>` for athlete-area pages only. |
| `app/views/athlete/events/register.php`    | `#onlineBlock` now renders an outstanding-amount line + a real *"Pay ₹X Online"* button + the `payOnline()` JS handler that runs the Razorpay round-trip. |
| `app/.htaccess`                            | Existing rewrite kept; added a `FilesMatch` deny block for `.env` / `.sql` / `.log` / dotfiles. |

---

## 2. SQL migration to run

The schema auto-applies on the next request. If you'd rather run it by hand,
paste this in **phpMyAdmin → SQL** (it's the same content as
`database/fix_razorpay_columns.sql`, idempotent):

```sql
ALTER TABLE event_registration_payments
  ADD COLUMN payment_method      ENUM('manual','epayment') NOT NULL DEFAULT 'manual' AFTER status,
  ADD COLUMN razorpay_order_id   VARCHAR(255) NULL                                    AFTER payment_method,
  ADD COLUMN razorpay_payment_id VARCHAR(255) NULL                                    AFTER razorpay_order_id,
  ADD COLUMN razorpay_signature  VARCHAR(512) NULL                                    AFTER razorpay_payment_id,
  ADD UNIQUE KEY uq_rzp_order (razorpay_order_id);
```

Existing manual rows continue to work — `payment_method` defaults to
`'manual'` so historical data is unchanged.

---

## 3. URL / route to test

1. Log in as an athlete (`/login`).
2. From the dashboard's **Active Events** card, click **Register** on an
   event whose **Payment Modes** include `online`.
3. Save Step 1 (Unit / NOC / Sport Events).
4. In Step 2, pick **Online Payment** → Razorpay Pay button appears.

The actual API URLs hit are:

```
POST /athlete/events/<event-hash>/pay/create-order
POST /athlete/events/<event-hash>/pay/verify
```

Both are CSRF-protected and athlete-auth-gated.

---

## 4. Test plan

Razorpay test mode card: **4111 1111 1111 1111**, any future expiry, any
3-digit CVV, OTP `1234` / `123456`.

| # | Action | Expected DB state on `event_registration_payments` |
| - | --- | --- |
| 1 | Click **Pay ₹X Online** → modal opens with the test card → submit. | One new row, `payment_method='epayment'`, `status='approved'`, `razorpay_order_id`/`payment_id`/`signature` all populated, `rejection_reason='AUTO: ePayment HMAC verified'`, `reviewed_at=now()`, `reviewed_by=NULL`. `event_registrations.payment_status='paid'`. |
| 2 | Click **Pay ₹X Online** → close the modal (X icon, dismiss). | One new row, `payment_method='epayment'`, `status='pending'`, only `razorpay_order_id` populated (audit of abandoned attempt). `event_registrations.payment_status` unchanged. |
| 3 | Use a card that fails (e.g. `4000 0000 0000 0002` in Razorpay test). | Row stays `status='pending'` (the `payment.failed` event doesn't auto-flip it; only verify does). UI shows the error description inline. |
| 4 | Tamper with the verify call — manually replay the create-order response with a forged signature. | Row goes to `status='rejected'`, `rejection_reason='AUTO: signature mismatch'`. `payment_status` recomputed accordingly (won't be `paid`). |

After each scenario, check `event_registrations.payment_status` —
`recomputeRegistrationPaymentStatus()` runs after both verify branches and
flips the header to `paid` only when approved transactions cover
`total_amount`.

---

## 5. Switching from test to live keys

You only edit the `.env` on the server, never any tracked file:

| File | Line(s) | Change |
| --- | --- | --- |
| `app/.env` (server only, gitignored) | `RAZORPAY_KEY_ID=` | replace `rzp_test_…` with the live `rzp_live_…` value from Razorpay dashboard. |
| `app/.env` (server only, gitignored) | `RAZORPAY_KEY_SECRET=` | replace with the live key secret. |

No code changes. The next request reads the new values via
`putenv()` → `getenv()` → `app/config/app.php['razorpay']`.

If you'd rather use cPanel's *PHP Environment Variables* UI instead of
`app/.env`, set `RAZORPAY_KEY_ID` and `RAZORPAY_KEY_SECRET` there and
the same code picks them up — `app/public/index.php`'s loader prefers
the existing process env, and `getenv()` reads it.

---

## 6. Manual operational steps

These don't block the integration shipping but should be done before
heavy production use:

1. **Confirm `php-curl` is installed** on the cPanel host. Check via
   *cPanel → MultiPHP INI Editor → Editor Mode* and verify `extension=curl`
   is loaded, or:
   ```
   php -m | grep -i curl
   ```
   The integration throws a clean *"PHP curl extension is required"*
   message if it isn't, but you'd rather catch this before going live.

2. **File permissions** on shared hosting: `app/.env` should be `600`
   (read-write for the cPanel user only). Confirm with:
   ```
   chmod 600 app/.env
   ```
   Same applies to `app/config/app.php` and `app/config/database.php` on
   environments where they're written (they're gitignored and live-edited).

3. **Webhook (recommended for production reliability):**
   The current flow verifies signatures on the client round-trip — if the
   athlete closes their browser between paying and the verify POST, the
   transaction can stay `pending` even though Razorpay successfully
   collected the money. Webhooks fix this:

   - In Razorpay dashboard → *Settings → Webhooks → Create*, point at
     `https://app.sportsmis.com/athlete/events/0/pay/webhook` (a future
     endpoint — not yet implemented; reach out when you want it added).
   - Subscribe to `payment.captured` and `payment.failed` events.
   - The webhook secret can also live in `.env` as `RAZORPAY_WEBHOOK_SECRET`.

   **Until that's in place, treat any "stuck pending" ePayment row that
   the athlete swears they paid for as something to verify in the
   Razorpay dashboard manually before approving.**

4. **First production payment test:** start with the smallest possible
   live event fee (₹1) and refund yourself from the Razorpay dashboard.
   Confirm:
   - Transaction row reaches `status='approved'`.
   - `event_registrations.payment_status='paid'`.
   - The athlete sees the registration as paid on `/athlete/my-registrations`.
   - The institution admin sees the transaction in the registration
     detail page (Approved Transactions count goes up).
