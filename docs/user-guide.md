# SportsMIS — Quick-start user guide

Six common workflows, in the order a new institution typically goes through
them. Each section is self-contained, so jump to whichever step you need.

---

## 1. Register an Institution

Use this when your club / school / academy is signing up to SportsMIS for the
first time. Anyone in the institution can do it — you become the institution
admin for the new account.

1. Go to **`/login`** (the sign-in page).
2. On the **Institutions & Clubs** card, click **Register**.
3. Fill the form:
   - **Institution / Club Name** — e.g. *Kerala State Sports Council*.
   - **SPOC Name** + **Mobile** — the single point of contact we'll reach if
     there's a question on the account.
   - **Email Address** — this becomes your login username.
   - **Address** — full postal address.
4. Click **Submit Registration**. You'll receive a verification email.
5. Open the email and click the verification link — set a password on the
   landing page.
6. You're now signed in as the **institution admin**. The next stop is the
   **Institution Profile** page; complete it before creating events.

> **Heads-up.** The registration certificate file on the profile page is
> **optional** — you can submit the profile without uploading one.

---

## 2. Request to Participate in an Event

Use this when another institution is running an event and you want your club
to take part as a participating unit.

1. Sign in as an institution admin at **`/login`** → Institutions & Clubs →
   **Login**.
2. From the dashboard, open **Browse Public Events** (or go to
   **`/institution/public-events`**).
3. The page lists every event whose organiser has flipped on
   *Allow Institution Join Requests*. Each row shows the event name, dates,
   organiser, and your current request status.
4. Click **Request to Participate** on the event you want to join.
   - You can optionally name your proposed unit on this event (e.g. "St.
     John's School" or "U-19 Squad").
5. The organising institution sees your request on their
   **Participation Requests** panel and approves or rejects it.
6. Once approved, the event shows up on your dashboard's
   **Events I'm Participating In** card with a green count badge.
7. Click that card and use **Open Unit Console** on the event row — that
   switches you into the unit-side screens **using your institution login**;
   you don't need a separate unit-user account.

---

## 3. Login as a Unit User

Two ways to reach the Unit Console, depending on how the event admin set
things up.

### A) Direct Unit User login (separate credentials)

Use this when the event admin created a Unit User account for you and emailed
the credentials.

1. Go to **`/unit/login`**.
2. Fill:
   - **Event Code** — the short identifier the event admin shared (e.g.
     `SHOOT-2026-01`).
   - **Email** + **Password** from the welcome email.
3. Click **Sign In** — you land on the Unit Portal scoped to that one event.

### B) Institution-as-Unit proxy (no separate password)

Use this if your institution has been **approved as a participating unit** on
another institution's event (see **§2**).

1. Sign in as the institution admin at **`/login`**.
2. Open **Events I'm Participating In** on the dashboard.
3. Click **Open Unit Console** on the event row.
4. You stay logged in as your institution but the screens are identical to a
   stand-alone Unit User's view. A blue banner across the top reminds you
   "*You are acting as a Unit on this event using your institution login*"
   with a *Switch back to Institution Dashboard* link.

The same Unit Portal menus (Dashboard, Registrations, Transactions, NOC,
Team Entry, Lane Allocation) show in both modes — they're gated by the event
admin's per-event settings, not by which login path you took.

---

## 4. Add an Athlete and Register

Use this when your unit has a new athlete you want to register for the
event's sport-events.

1. From the Unit Portal **Dashboard**, click **Add Athlete** (top right).
2. Fill the athlete profile:
   - **Full Name** *(required)*, **Gender** *(required)*, **Date of Birth**
     *(required)*.
   - **Mobile** *(optional)*, **Email** *(optional — leave blank for a
     **managed athlete** who won't get a login; supply an email if you want
     the athlete to be able to claim their own account later)*.
   - **Aadhaar Number** + **Aadhaar Proof File** — **required if the event
     admin set Aadhaar to *Mandatory*** on the event's Registration Settings,
     otherwise optional.
   - **Passport Photo** *(optional)*, **Address** *(optional)*.
3. Click **Create Athlete & Start Registration**. You land on the
   per-athlete registration page.
4. **Pick sport-events:**
   - The dropdown lists only events the athlete is eligible for — filtered
     by gender, by the event's Age Category Set (Master / CBSE), and by the
     athlete's age category derived from DOB.
   - Pick one, click **Add Event**. Repeat for every event the athlete is
     entering.
   - To remove an event, click the × on its row in the table below. The
     **Total Demand** in the footer updates each time.
5. **Add payment transactions:**
   - In the *Add Payment Transaction* panel below, enter Date, Transaction
     Number, Amount (defaults to outstanding balance), and upload the proof
     file. Click **+**.
   - You can add multiple transactions — the **Balance** at the top of the
     panel shows what's still owed. It turns green when the math zeroes out.
6. **Submit for Review.** The button at the bottom is **disabled** until
   the sum of non-rejected transactions equals the Total Demand. Click it
   once the balance is zero — the registration goes to the event admin
   queue.

> **Tip.** You can come back later and continue editing as long as the
> registration is still in **Draft** or **Returned** state. Once it's in
> Pending Review you'll need the admin to return it to keep editing.

---

## 5. Add a Team Entry

Use this when an event has team competitions and you're registering a team
from your unit.

> Team Entry must be **enabled per event** (Registration Settings → Team
> Entry → tick *Through Unit User Login*). If the menu doesn't show in your
> Unit Portal, ask the event admin to enable it for you.

1. In the Unit Portal top nav, click **Team Entry**.
2. Click **+ New Team Entry**.
3. **Event Category** dropdown — lists only categories that have at least
   one sport-event configured with **Entry Mode = Both** or **Team only**.
4. **Sport Event** dropdown — lists only events with Entry Mode = Both or
   Team only under the chosen category. Each option shows the configured
   **Team Size** (defaults to 3, set by the event admin per sport-event).
5. **Unit / Club** — picks the unit the team belongs to. Auto-selected for
   single-unit operators.
6. **Team Name** — your choice (e.g. *St. John's A*, *Senior Squad*).
7. **Members** — add athletes from the *Members* dropdown one by one.
   - For events with **Entry Mode = Both**, members must already have an
     individual registration on that sport-event (pending OR approved).
   - For **Team only** events, any athlete from your unit on a non-rejected
     event-registration row is eligible — no individual entry needed.
   - The captain is the first member you add.
8. **Save Team Entry.** When you're a Unit User (i.e. you have to pay
   yourself, not Event Staff), the next step is to attach a payment:
   - Date, Transaction Number, Amount, Proof file (same shape as
     individual-registration transactions).
9. Click **Submit for Review** — the team goes to the event admin queue.

---

## 6. Add a Bulk Fund Transfer

Use this when you've paid the event in one bank transaction covering many
athletes (the common case for clubs paying entry fees for a whole squad).

> This action is the time-saver in the Unit Portal: one bank transaction →
> one form submission → the system creates one payment row per athlete,
> each using that athlete's outstanding balance.

1. In the Unit Portal top nav, click **Registrations**.
2. Use the filters at the top to find the rows you're paying for:
   - **Athlete name** search box.
   - **Payment Status** — useful values: *Not paid*, *Partial*, *Demand fully
     paid*.
   - **Registration Status** — *Draft*, *Submitted*, *Approved*, *Rejected*,
     *Returned*.
3. **Tick the checkbox** at the left of every row you want to include in
   the bulk transaction. The header row's checkbox toggles **Select All**
   across the currently-visible rows.
   - Rows that aren't eligible (e.g. status is *Approved* or balance is
     zero) have their checkbox **disabled** with a tooltip.
4. The **Log Bulk Payment Transaction** button at the top right activates
   once you have at least one eligible row selected. The blue chip on the
   button shows how many are picked.
5. Click the button — a modal opens with:
   - **Date** (defaults to today, editable).
   - **Transaction Number** (you fill).
   - **# Transactions** + **Total Amount** — *read-only mirrors* of the
     selection. They update live as you change which rows are ticked.
   - **Proof File** — one file, attached to every payment row the system
     creates.
   - **Selected Athletes** — a list showing the per-athlete balance that
     will be claimed.
6. Click **Save Bulk Transaction**. The system:
   - Re-derives each athlete's outstanding balance **server-side** (so a
     tampered client can't fake amounts).
   - Creates one **pending** payment row per selected registration, each
     with the registration's own outstanding-balance amount.
   - All rows share the same Date, Transaction Number, and Proof file.
   - Rows whose balance is already zero or whose registration is locked
     are silently skipped — the flash message tells you "*N created, M
     skipped*".
7. Each athlete's registration page now shows the new pending payment in
   its **Payment Transactions** table. Submit each registration for review
   when its Balance is zero (or, if it already met the threshold, it can
   submit immediately).

---

## Common questions

**"My event category / event dropdown is empty when I click Team Entry."**
The event admin hasn't configured any sport-event row with Entry Mode set to
*Both* or *Team only*. Ask them to flip the Entry Mode dropdown on the
relevant rows of *Manage Event → Sports in this Event*.

**"I see 'No DOB on the athlete profile — age filter skipped' on the
registration page."**
The athlete record has no date of birth, so the system can't filter
sport-events by age category. Either set the DOB on the athlete profile, or
leave it as-is and pick events manually from the unfiltered list.

**"The submit-for-review button is disabled."**
The sum of non-rejected transactions doesn't equal the Total Demand. The
inline hint below the button tells you the exact gap.

**"How do I know if a team-entry member has paid?"**
Open the team-entry detail page. Each member row shows the same payment
status indicator as their individual registration.

---

*Last reviewed: keep this file alongside your release notes whenever a
workflow changes.*
