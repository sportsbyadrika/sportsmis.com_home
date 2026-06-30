<?php
namespace Controllers;

use Core\{Controller, Auth, FileUpload};
use Models\{UnitUser, Event, EventUnit, EventRegistration, EventRegistrationPayment, EventDocument, Athlete, Schema, Noc, Institution};

/**
 * Separate login portal + dashboard for Unit / Institution / Club users.
 * Auth lives in $_SESSION['unit_user'] (independent of $_SESSION['user']
 * so a unit user can coexist with another role on the same browser).
 *
 * Forward-looking placeholders (Team Entry, Lane Allocation) are kept as
 * clearly-marked stubs so future prompts can drop into them.
 */
class UnitController extends Controller
{
    private array $unitUser;
    private array $event;
    private bool $isInstitutionProxy = false;
    private ?array $institutionProxy = null;

    private function boot(): void
    {
        try { Schema::ensureUnitUsers(); } catch (\Throwable $e) {}
        try { Schema::ensureInstitutionAsUnit(); } catch (\Throwable $e) {}

        // Path 1 — institution-as-unit proxy. An institution_admin
        // opens the unit console for an event they were approved to
        // join. Their own session stays the source of truth; we just
        // synthesise the same $unitUser shape so the rest of the
        // controller code works unchanged.
        $proxy = $_SESSION['institution_as_unit'] ?? null;
        if ($proxy && Auth::check() && Auth::is('institution_admin')) {
            $event = Event::findById((int)$proxy['event_id']);
            $eu    = EventUnit::find((int)$proxy['unit_id']);
            $inst  = Institution::findByUserId((int)Auth::id());
            $valid = $event && $eu && $inst
                  && (int)$eu['event_id'] === (int)$event['id']
                  && (int)($eu['linked_institution_id'] ?? 0) === (int)$proxy['institution_id']
                  && (int)$inst['id'] === (int)$proxy['institution_id'];
            if (!$valid) {
                unset($_SESSION['institution_as_unit'], $_SESSION['unit_active_unit_id']);
                $this->redirect('/institution/participating-events',
                    'That participation is no longer active.', 'warning');
            }
            $event['event_code'] = $event['event_code'] ?? \ensureEventCode((int)$event['id']);
            $this->unitUser = [
                'id'       => 0,
                'name'     => (string)$inst['name'],
                'email'    => (string)(Auth::user()['email'] ?? ''),
                'event_id' => (int)$event['id'],
                'status'   => 'active',
            ];
            $this->event              = $event;
            $this->isInstitutionProxy = true;
            $this->institutionProxy   = [
                'institution_id' => (int)$inst['id'],
                'unit_id'        => (int)$eu['id'],
            ];
            // Pin the active unit so the dashboard renders the linked one.
            $_SESSION['unit_active_unit_id'] = (int)$eu['id'];
            return;
        }

        // Path 2 — regular unit_user.
        if (!Auth::unitUserCheck()) {
            $this->redirect('/unit/login', 'Please sign in to continue.', 'warning');
        }
        $session = Auth::unitUser();
        $u = UnitUser::findById((int)$session['id']);
        if (!$u || $u['status'] !== 'active') {
            Auth::unitUserLogout();
            $this->redirect('/unit/login', 'Your unit user account is not active.', 'error');
        }
        $event = Event::findById((int)$u['event_id']);
        if (!$event) {
            Auth::unitUserLogout();
            $this->redirect('/unit/login', 'Event no longer exists.', 'error');
        }
        $event['event_code'] = $event['event_code'] ?? \ensureEventCode((int)$event['id']);
        $this->unitUser = $u;
        $this->event    = $event;
    }

    /**
     * Units this caller can act on. In normal mode delegates to
     * UnitUser::assignmentsFor; in institution-proxy mode returns the
     * single event_unit linked to the proxying institution.
     */
    private function assignedUnits(): array
    {
        if ($this->isInstitutionProxy) {
            $eu = EventUnit::find((int)$this->institutionProxy['unit_id']);
            return $eu ? [$eu] : [];
        }
        return UnitUser::assignmentsFor((int)$this->unitUser['id']);
    }

    private function assignedUnitIds(): array
    {
        if ($this->isInstitutionProxy) {
            return [(int)$this->institutionProxy['unit_id']];
        }
        return UnitUser::assignmentIds((int)$this->unitUser['id']);
    }

    // ── Auth ─────────────────────────────────────────────────────────────────

    public function loginForm(): void
    {
        if (Auth::unitUserCheck()) $this->redirect('/unit/dashboard');
        $this->renderWith('auth', 'unit/login', [
            'flash' => $this->flash(),
        ]);
    }

    public function login(): void
    {
        $this->verifyCsrf();
        $code     = trim((string)($_POST['event_code'] ?? ''));
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        $user = UnitUser::attempt($code, $email, $password);
        if (!$user) {
            $this->redirect('/unit/login', 'Invalid Event Code, email or password.', 'error');
        }
        Auth::unitUserLogin($user);
        $this->redirect('/unit/dashboard');
    }

    public function logout(): void
    {
        Auth::unitUserLogout();
        $this->redirect('/unit/login', 'Signed out.');
    }

    public function changePassword(): void
    {
        $this->boot();
        $this->verifyCsrf();
        if ($this->isInstitutionProxy) {
            $this->redirect('/unit/dashboard',
                'Password changes happen on your Institution account, not here.', 'warning');
        }
        $current = (string)($_POST['current_password']      ?? '');
        $new     = (string)($_POST['password']              ?? '');
        $confirm = (string)($_POST['password_confirmation'] ?? '');
        $back    = '/unit/dashboard';
        if ($current === '' || $new === '' || $confirm === '') {
            $this->redirect($back, 'All three password fields are required.', 'error');
        }
        if (strlen($new) < 8) {
            $this->redirect($back, 'New password must be at least 8 characters.', 'error');
        }
        if ($new !== $confirm) {
            $this->redirect($back, 'New password and confirmation do not match.', 'error');
        }
        if (!password_verify($current, $this->unitUser['password'])) {
            $this->redirect($back, 'Current password is incorrect.', 'error');
        }
        UnitUser::updatePassword((int)$this->unitUser['id'], Auth::hashPassword($new));
        $this->redirect($back, 'Password updated successfully.');
    }

    // ── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(): void
    {
        $this->boot();
        $units = $this->assignedUnits();
        if (!$units) {
            $this->renderWith('unit', 'unit/dashboard', [
                'unit_user'     => $this->unitUser,
                'event'         => $this->event,
                'units'         => [],
                'active_unit'   => null,
                'stats'         => ['total' => 0, 'approved' => 0, 'demand' => 0.0, 'claimed' => 0.0],
                'pivot_rows'    => [],
                'flash'         => $this->flash(),
            ]);
            return;
        }
        // Pick the active unit from session, ?unit_id=, or first by default.
        $requested = (int)($_GET['unit_id'] ?? ($_SESSION['unit_active_unit_id'] ?? 0));
        $active = null;
        foreach ($units as $u) if ((int)$u['id'] === $requested) { $active = $u; break; }
        if (!$active) $active = $units[0];
        $_SESSION['unit_active_unit_id'] = (int)$active['id'];

        $stats     = $this->statsForUnit((int)$active['id']);
        $pivotRows = $this->sportEventPivotForUnit((int)$active['id']);

        $this->renderWith('unit', 'unit/dashboard', [
            'unit_user'     => $this->unitUser,
            'event'         => $this->event,
            'units'         => $units,
            'active_unit'   => $active,
            'stats'         => $stats,
            'pivot_rows'    => $pivotRows,
            'flash'         => $this->flash(),
        ]);
    }

    /** GET /unit/athletes/{regId} — read-only registration detail. */
    public function athleteShow(string $regId): void
    {
        $this->boot();
        $rid = \hid_reg_decode($regId);
        $reg = EventRegistration::withProfile((int)$rid);
        if (!$reg || (int)$reg['event_id'] !== (int)$this->event['id']) $this->abort(404);

        // Authorise: the registration's unit_id must be among the unit_user's
        // assigned units. Free-text "Other" units are intentionally NOT
        // surfaced to unit users because they aren't tied to any specific
        // unit account.
        $allowedUnitIds = $this->assignedUnitIds();
        if (empty($reg['unit_id']) || !in_array((int)$reg['unit_id'], $allowedUnitIds, true)) {
            $this->abort(403);
        }
        $athlete = Athlete::findById((int)$reg['athlete_id']);

        // Scope the picker to the event's Age Category Set + the
        // athlete's gender + the athlete's eligible age brackets
        // (from DOB → age_categories bounds, plus "also eligible"
        // upgrades). The Unit User then only sees events the athlete
        // is actually eligible for.
        //
        // Graceful degradation when a strict filter blanks the picker:
        //   - No DOB on file ............... drop the age filter
        //   - DOB set but eligibility=[] ... drop the age filter (legacy
        //                                    age_categories rows may have
        //                                    no min/max/year bounds set)
        //   - Strict filter returns 0 rows.. retry with gender+set only,
        //                                    then with the unfiltered list
        // Each fallback sets $filterNote so the view can explain why the
        // picker isn't gender/age-scoped any more.
        $ageSet       = (string)($this->event['age_category_set'] ?? 'master');
        $athGen       = (string)($athlete['gender'] ?? '');
        // Age is reckoned on the event's configured Age Calculation Date,
        // defaulting to the event start date. Eligibility + the displayed
        // age category both respect this date and the event's age set.
        $ageCalcDate  = !empty($this->event['age_calc_date'])
                          ? (string)$this->event['age_calc_date']
                          : (string)($this->event['event_date_from'] ?? '');
        $eligibleCats = Athlete::eligibleAgeCategories(
            $athlete['date_of_birth'] ?? null, $ageCalcDate ?: null, $ageSet);

        $filterNote = '';
        $eventId    = (int)$this->event['id'];

        if (!empty($athlete['date_of_birth']) && $eligibleCats) {
            $rows = Event::getSports($eventId, $ageSet, $athGen, $eligibleCats);
            if (!$rows) {
                // Strict filter returned nothing — drop the age constraint
                // so the unit user can still pick something.
                $rows = Event::getSports($eventId, $ageSet, $athGen, null);
                if ($rows) {
                    $filterNote = 'No events match the athlete\'s age category exactly — showing every gender-matched event instead.';
                }
            }
        } else {
            // No DOB OR no eligible categories — skip the age filter.
            $rows = Event::getSports($eventId, $ageSet, $athGen, null);
            $filterNote = !empty($athlete['date_of_birth'])
                ? 'No active Age Categories cover this athlete\'s DOB — age filter skipped.'
                : 'No DOB on the athlete profile — age filter skipped.';
        }
        if (!$rows) {
            // Even gender + set is blanking it. Last fallback: every
            // event_sport configured on the event, regardless of
            // gender / set / age. Calls out the picker as unfiltered.
            $rows = Event::getSports($eventId);
            if ($rows) {
                $filterNote = 'No gender / age-category match — showing every sport-event configured on this event.';
            }
        }
        $this->renderWith('unit', 'unit/athlete', [
            'unit_user'    => $this->unitUser,
            'event'        => $this->event,
            'registration' => $reg,
            'athlete'      => $athlete,
            'items'        => EventRegistration::items((int)$rid),
            'sport_items'  => \Models\RegistrationSportItem::forRegistration((int)$rid),
            'payments'     => EventRegistrationPayment::forRegistration((int)$rid),
            'pay_totals'   => EventRegistrationPayment::totals((int)$rid),
            'documents'    => EventDocument::activeForEvent((int)$this->event['id']),
            'event_sports' => $rows,
            'eligible_age_categories' => $eligibleCats,
            'age_category_label' => implode(', ', Athlete::baseAgeCategories(
                $athlete['date_of_birth'] ?? null, $ageCalcDate ?: null, $ageSet)),
            'age_calc_date' => $ageCalcDate ?: null,
            'dob_proof_types' => Athlete::getDobProofTypes(),
            'filter_note'  => $filterNote,
            'can_edit'     => !empty($this->event['allow_unit_registration'])
                              && EventRegistration::isEditable($reg),
            'flash'        => $this->flash(),
        ]);
    }

    /**
     * POST /unit/athletes/{id}/items — Unit user picks the sport-events
     * this athlete will compete in. Replaces the registration's items in
     * one shot via syncItems(), then refreshes the total amount.
     */
    /**
     * POST /unit/athletes/{id}/items/add — append a single sport-event
     * to the registration. Auto-refreshes the demand transaction so the
     * Payment Transactions panel always reflects the running total.
     */
    public function addAthleteItem(string $regId): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {
            error_log('[unit/addAthleteItem:ensureSportHierarchy] ' . $e->getMessage());
        }
        try { Schema::ensureUnitRegistration(); } catch (\Throwable $e) {}
        $reg = $this->loadEditableRegistration($regId);
        $esId = (int)($_POST['event_sport_id'] ?? 0);
        if ($esId <= 0) {
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'Pick a sport event to add.', 'warning');
        }

        // Enforce the event admin's caps before adding. Re-adding an item
        // the registration already has is an idempotent no-op, so caps are
        // only checked for genuinely new picks.
        $back = '/unit/athletes/' . \hid_reg((int)$reg['id']);
        $meta = Event::sportRowMeta((int)$this->event['id'], $esId);
        if (!$meta) {
            $this->redirect($back, 'That sport event is not part of this event.', 'warning');
        }
        $counts     = EventRegistration::itemModeCounts((int)$reg['id']);
        $alreadyHas = in_array($esId, $counts['event_sport_ids'], true);
        $isTeam     = ($meta['team_entry_mode'] === 'team_only');
        if (!$alreadyHas) {
            // a) Max Individual / Team events an athlete can participate.
            $maxIndiv = (int)($this->event['max_individual_events'] ?? 0);
            $maxTeam  = (int)($this->event['max_team_events'] ?? 0);
            if (!$isTeam && $maxIndiv > 0 && $counts['individual'] >= $maxIndiv) {
                $this->redirect($back,
                    "This athlete already has the maximum of {$maxIndiv} individual event(s) allowed for this event.",
                    'warning');
            }
            if ($isTeam && $maxTeam > 0 && $counts['team'] >= $maxTeam) {
                $this->redirect($back,
                    "This athlete already has the maximum of {$maxTeam} team event(s) allowed for this event.",
                    'warning');
            }
            // b) Max members per unit for this sport-event.
            $maxUnit = $meta['max_members_per_unit'] !== null ? (int)$meta['max_members_per_unit'] : 0;
            $unitId  = (int)($reg['unit_id'] ?? 0);
            if ($maxUnit > 0 && $unitId > 0) {
                $used = EventRegistration::unitCountForSportEvent(
                    (int)$this->event['id'], $unitId, $esId, (int)$reg['id']);
                if ($used >= $maxUnit) {
                    $this->redirect($back,
                        "Your unit has reached the maximum of {$maxUnit} member(s) allowed for this sport-event.",
                        'warning');
                }
            }
            // c) Single age-category rule: every event on the registration
            //    must share one age category. Block a pick whose age
            //    category differs from what's already added.
            $newAgeCatId = (int)($meta['age_category_id'] ?? 0);
            if ($newAgeCatId > 0) {
                foreach (EventRegistration::itemAgeCategories((int)$reg['id']) as $c) {
                    $cid = (int)($c['age_category_id'] ?? 0);
                    if ($cid > 0 && $cid !== $newAgeCatId) {
                        $this->redirect($back, sprintf(
                            'This athlete already has event(s) in the %s age category. '
                            . 'An athlete can register in only one age category — remove the existing '
                            . 'event(s) before adding one from %s.',
                            (string)($c['age_category_name'] ?? 'another'),
                            (string)($meta['age_category_name'] ?? 'a different category')),
                            'warning');
                    }
                }
            }
        }

        try {
            $total = EventRegistration::addItem((int)$reg['id'], $esId);
            EventRegistration::updateHeader((int)$reg['id'], ['total_amount' => $total]);
            // Wipe any legacy auto-demand placeholder rows; the demand
            // is shown on the registration via dedicated Demand /
            // Balance columns, not as a fake transaction.
            EventRegistrationPayment::purgeDemandRows((int)$reg['id']);
        } catch (\Throwable $e) {
            error_log('[unit/addAthleteItem] ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'Could not add the event: ' . $e->getMessage(), 'error');
        }
        $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
            sprintf('Event added. Total demand: ₹%s.', number_format((float)$total, 2)));
    }

    /**
     * POST /unit/athletes/{id}/items/remove — drop a single sport-event
     * from the registration. Refreshes the demand row.
     */
    public function removeAthleteItem(string $regId): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {
            error_log('[unit/removeAthleteItem:ensureSportHierarchy] ' . $e->getMessage());
        }
        $reg = $this->loadEditableRegistration($regId);
        $esId = (int)($_POST['event_sport_id'] ?? 0);
        try {
            $total = EventRegistration::removeItem((int)$reg['id'], $esId);
            EventRegistration::updateHeader((int)$reg['id'], ['total_amount' => $total]);
            // Wipe any legacy auto-demand placeholder rows; the demand
            // is shown on the registration via dedicated Demand /
            // Balance columns, not as a fake transaction.
            EventRegistrationPayment::purgeDemandRows((int)$reg['id']);
        } catch (\Throwable $e) {
            error_log('[unit/removeAthleteItem] ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'Could not remove the event: ' . $e->getMessage(), 'error');
        }
        $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
            $total > 0
                ? sprintf('Event removed. Total demand: ₹%s.', number_format((float)$total, 2))
                : 'Event removed — pending demand cleared.');
    }

    public function saveAthleteItems(string $regId): void
    {
        $this->boot();
        $this->verifyCsrf();
        // Self-heal the schema — same reasoning as storeAthlete: the
        // Unit-User flow can be the only one running, so the
        // event_registration_payments / registration_flow columns
        // we touch must be present.
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {
            error_log('[unit/saveAthleteItems:ensureSportHierarchy] ' . $e->getMessage());
        }
        $reg = $this->loadEditableRegistration($regId);

        $picked = $_POST['event_sport_ids'] ?? [];
        if (!is_array($picked)) $picked = [];
        $picked = array_values(array_unique(array_map('intval', $picked)));

        try {
            $total = EventRegistration::syncItems((int)$reg['id'], $picked);
            EventRegistration::updateHeader((int)$reg['id'], ['total_amount' => $total]);
            // Auto-create / refresh the "demand" placeholder transaction so
            // the Payment Transactions panel always reflects what's owed.
            // Wipe any legacy auto-demand placeholder rows; the demand
            // is shown on the registration via dedicated Demand /
            // Balance columns, not as a fake transaction.
            EventRegistrationPayment::purgeDemandRows((int)$reg['id']);
        } catch (\Throwable $e) {
            error_log('[unit/saveAthleteItems] ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'Could not save the selection: ' . $e->getMessage(), 'error');
        }

        $msg = $picked
            ? sprintf('Sport events saved. Total demand: ₹%s.', number_format((float)$total, 2))
            : 'All sport events removed — pending demand cleared.';
        $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']), $msg);
    }

    /**
     * POST /unit/athletes/{id}/payments — record a manual transaction.
     * Mirrors the athlete-side registerAddPayment so multiple
     * transactions can accumulate towards the total demand. Validated
     * server-side so a stuck JS submit can't sneak a bad row through.
     */
    public function addAthletePayment(string $regId): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {
            error_log('[unit/addAthletePayment:ensureSportHierarchy] ' . $e->getMessage());
        }
        $reg = $this->loadEditableRegistration($regId);

        $txDate = trim((string)($_POST['transaction_date']   ?? ''));
        $txNum  = trim((string)($_POST['transaction_number'] ?? ''));
        $amount = (float)($_POST['transaction_amount']        ?? 0);

        if ($txDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $txDate)) {
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'Enter a valid transaction date.', 'error');
        }
        if ($txNum === '' || $amount <= 0) {
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'Transaction number and amount are required.', 'error');
        }
        if (empty($_FILES['transaction_proof']['name'])) {
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'Attach the transaction proof file.', 'error');
        }
        try {
            $proof = (new FileUpload())->upload($_FILES['transaction_proof'], 'registrations');
            EventRegistrationPayment::create([
                'registration_id'    => (int)$reg['id'],
                'event_id'           => (int)$this->event['id'],
                'transaction_date'   => $txDate,
                'transaction_number' => $txNum,
                'amount'             => $amount,
                'proof_file'         => $proof,
                'payment_method'     => 'manual',
                'status'             => 'pending',
            ]);
            EventRegistration::updateHeader((int)$reg['id'], ['payment_mode' => 'manual']);
            EventRegistrationPayment::recomputeRegistrationPaymentStatus((int)$reg['id']);
        } catch (\Throwable $e) {
            error_log('[unit/addAthletePayment] ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'Could not save the transaction: ' . $e->getMessage(), 'error');
        }
        $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
            sprintf('Transaction added (₹%s).', number_format($amount, 2)));
    }

    /**
     * POST /unit/athletes/{id}/payments/remove — drop a non-demand,
     * non-approved transaction row.
     */
    public function removeAthletePayment(string $regId): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = $this->loadEditableRegistration($regId);
        $payId = (int)($_POST['payment_id'] ?? 0);
        if ($payId <= 0) {
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'Pick a transaction to remove.', 'error');
        }
        $pay = EventRegistrationPayment::find($payId);
        if (!$pay || (int)$pay['registration_id'] !== (int)$reg['id']) {
            $this->abort(404);
        }
        $method = (string)($pay['payment_method'] ?? 'manual');
        if ($method === 'demand') {
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'The demand row is auto-managed and can\'t be removed manually.', 'warning');
        }
        if ($method === 'epayment' || ($pay['status'] ?? '') === 'approved') {
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'Approved / ePayment transactions are locked. Ask the event admin to reject first.', 'warning');
        }
        EventRegistrationPayment::deleteRow($payId);
        EventRegistrationPayment::recomputeRegistrationPaymentStatus((int)$reg['id']);
        $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
            'Transaction removed.');
    }

    /**
     * POST /unit/athletes/{id}/submit — flip the draft / returned
     * registration to admin-review state. Blocked unless every demand
     * unit is matched by a corresponding transaction so the event
     * admin doesn't have to chase up missing money.
     */
    public function submitAthleteRegistration(string $regId): void
    {
        $this->boot();
        $this->verifyCsrf();
        $reg = $this->loadEditableRegistration($regId);

        if (!EventRegistration::items((int)$reg['id'])) {
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'Pick at least one sport event before submitting.', 'warning');
        }

        // Gate: sum of all real (non-demand) transactions — counting
        // both pending and approved as "claimed against the demand" —
        // must equal the total_amount, so the unit doesn't submit
        // half-paid. Approved alone would be too strict before the
        // admin has reviewed; pending+approved matches what the Unit
        // User has actually claimed to have paid.
        $demand    = (float)EventRegistration::sumFee((int)$reg['id']);
        $claimed   = (float)EventRegistrationPayment::sumClaimed((int)$reg['id']);
        $epsilon   = 0.005;
        if ($demand > 0 && abs($demand - $claimed) > $epsilon) {
            $msg = sprintf(
                'Cannot submit: transactions total ₹%s but the demand is ₹%s. '
                . 'Add or remove transactions so they match before submitting.',
                number_format($claimed, 2),
                number_format($demand, 2)
            );
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']), $msg, 'warning');
        }

        EventRegistration::updateHeader((int)$reg['id'], [
            'status'              => 'pending',
            'admin_review_status' => 'pending',
            'submitted_at'        => date('Y-m-d H:i:s'),
        ]);
        $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
            'Registration submitted to the event administrator for review.');
    }

    /**
     * POST /unit/athletes/{id}/profile — edit the managed athlete's
     * profile from the registration page. Available only while the
     * registration is still editable (Draft / Returned), i.e. up to
     * submission. File inputs (photo / Aadhaar / DOB proof) are optional
     * and only replace the stored copy when a new file is uploaded.
     */
    public function saveAthleteProfile(string $regId): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureAthleteDobProof(); } catch (\Throwable $e) {}
        $reg       = $this->loadEditableRegistration($regId);
        $athleteId = (int)$reg['athlete_id'];
        $back      = '/unit/athletes/' . \hid_reg((int)$reg['id']);

        $name    = trim((string)($_POST['name']          ?? ''));
        $gender  = strtolower(trim((string)($_POST['gender'] ?? '')));
        $dob     = trim((string)($_POST['date_of_birth'] ?? ''));
        $mobile  = trim((string)($_POST['mobile']        ?? ''));
        $address = trim((string)($_POST['address']       ?? ''));
        $aadhaar = preg_replace('/\s+/', '', (string)($_POST['id_proof_number'] ?? ''));
        $pwd     = strtolower(trim((string)($_POST['pwd_status'] ?? 'no')));
        if (!in_array($pwd, ['no', 'deaf', 'para'], true)) $pwd = 'no';

        $dobProofTypeId  = (int)($_POST['dob_proof_type_id'] ?? 0);
        $dobProofNumber  = trim((string)($_POST['dob_proof_number'] ?? ''));
        $allowedDobTypes = array_map('intval', array_column(Athlete::getDobProofTypes(), 'id'));
        if ($dobProofTypeId > 0 && !in_array($dobProofTypeId, $allowedDobTypes, true)) {
            $dobProofTypeId = 0;
        }

        // Per-event proof requirements. 'hide' removes the field from the
        // form entirely — when hidden we leave any stored value untouched
        // rather than wiping it.
        $aadhaarReq        = $this->event['aadhaar_required']   ?? 'optional';
        $aadhaarMandatory  = $aadhaarReq === 'mandatory';
        $aadhaarHide       = $aadhaarReq === 'hide';
        $dobProofReq       = $this->event['dob_proof_required'] ?? 'optional';
        $dobProofMandatory = $dobProofReq === 'mandatory';
        $dobProofHide      = $dobProofReq === 'hide';
        $existing          = Athlete::findById($athleteId) ?: [];

        $errors = [];
        if ($name === '')                                          $errors[] = 'Full name is required.';
        if (!in_array($gender, ['male', 'female', 'other'], true)) $errors[] = 'Pick the athlete\'s gender.';
        if ($dob === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) $errors[] = 'Enter a valid date of birth.';
        if ($mobile !== '' && !preg_match('/^[6-9]\d{9}$/', $mobile)) $errors[] = 'Enter a valid 10-digit mobile number.';
        if (!$aadhaarHide) {
            if ($aadhaar !== '' && !preg_match('/^\d{12}$/', $aadhaar)) $errors[] = 'Aadhaar must be 12 digits or left blank.';
            if ($aadhaarMandatory && $aadhaar === '')                  $errors[] = 'Aadhaar number is required for this event.';
            // Aadhaar dedupe — block reusing an Aadhaar that belongs to a
            // different athlete.
            if (!$errors && $aadhaar !== '') {
                $hit = Athlete::findExistingForUnitDedupe($aadhaar, null, null);
                if ($hit && (int)$hit['id'] !== $athleteId) {
                    $errors[] = 'Another athlete (' . (string)$hit['name'] . ') already uses this Aadhaar number.';
                }
            }
        }
        if (!$dobProofHide && $dobProofMandatory) {
            if ($dobProofTypeId <= 0) $errors[] = 'Date of Birth proof type is required for this event.';
            if ($dobProofNumber === '') $errors[] = 'Date of Birth proof document number is required for this event.';
            $hasDobFile = !empty($existing['dob_proof_file']) || !empty($_FILES['dob_proof_file']['name']);
            if (!$hasDobFile) $errors[] = 'Date of Birth proof file is required for this event.';
        }
        if ($errors) {
            $this->redirect($back, implode(' ', $errors), 'error');
        }

        $data = [
            'name'              => mb_substr($name, 0, 255),
            'gender'            => $gender,
            'date_of_birth'     => $dob,
            'mobile'            => $mobile ?: null,
            'address'           => $address ?: null,
            'pwd_status'        => $pwd,
        ];
        // Only touch proof columns when the field is collected — hiding it
        // must not erase previously-stored data.
        if (!$aadhaarHide) {
            $data['id_proof_number'] = $aadhaar ?: null;
        }
        if (!$dobProofHide) {
            $data['dob_proof_type_id'] = $dobProofTypeId ?: null;
            $data['dob_proof_number']  = $dobProofNumber ?: null;
        }
        try {
            if (!empty($_FILES['passport_photo']['name'])) {
                $data['passport_photo'] = (new FileUpload())->upload($_FILES['passport_photo'], 'athletes/photos', true);
            }
            if (!$aadhaarHide && !empty($_FILES['id_proof_file']['name'])) {
                $data['id_proof_file'] = (new FileUpload())->upload($_FILES['id_proof_file'], 'athletes/idproofs');
            }
            if (!$dobProofHide && !empty($_FILES['dob_proof_file']['name'])) {
                $data['dob_proof_file'] = (new FileUpload())->upload($_FILES['dob_proof_file'], 'athletes/idproofs');
            }
            Athlete::updateProfile($athleteId, $data);
        } catch (\Throwable $e) {
            error_log('[unit/saveAthleteProfile] ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->redirect($back, 'Could not save the profile: ' . $e->getMessage(), 'error');
        }
        $this->redirect($back, 'Athlete profile updated.');
    }

    /**
     * Shared guard for the two write actions above: the registration
     * must belong to one of the Unit User's assigned units, the event
     * must currently allow Unit-driven registration, and the lock
     * state machine must still permit edits.
     */
    private function loadEditableRegistration(string $regId): array
    {
        $rid = \hid_reg_decode($regId);
        $reg = EventRegistration::withProfile((int)$rid);
        if (!$reg || (int)$reg['event_id'] !== (int)$this->event['id']) $this->abort(404);
        $allowed = $this->assignedUnitIds();
        if (empty($reg['unit_id']) || !in_array((int)$reg['unit_id'], $allowed, true)) {
            $this->abort(403);
        }
        if (empty($this->event['allow_unit_registration'])) {
            $this->redirect('/unit/dashboard',
                'Unit-driven registration is not enabled for this event.', 'error');
        }
        if (!EventRegistration::isEditable($reg)) {
            $this->redirect('/unit/athletes/' . \hid_reg((int)$reg['id']),
                'This registration is locked. Contact the event administrator for changes.', 'warning');
        }
        return $reg;
    }

    /**
     * Legacy entry point — Team Entry is now a shared module served from
     * /team-entry (TeamEntryController). Kept so old bookmarks still work.
     */
    public function teamEntryIndex(): void
    {
        $this->redirect('/team-entry');
    }

    /**
     * GET /unit/athletes/new — form used by the Unit User to create a
     * brand-new managed athlete and start a registration draft for them.
     * Only available when the event admin has flipped on
     * events.allow_unit_registration.
     */
    public function addAthleteForm(): void
    {
        $this->boot();
        try { Schema::ensureUnitRegistration(); } catch (\Throwable $e) {}
        try { Schema::ensureAthleteDobProof();   } catch (\Throwable $e) {}
        if (empty($this->event['allow_unit_registration'])) {
            $this->redirect('/unit/dashboard',
                'Unit-driven registration is not enabled for this event.', 'warning');
        }
        $units = $this->assignedUnits();
        if (!$units) {
            $this->redirect('/unit/dashboard',
                'No Unit / Club is assigned to your account yet.', 'warning');
        }
        $this->renderWith('unit', 'unit/athletes-new', [
            'unit_user' => $this->unitUser,
            'event'     => $this->event,
            'units'     => $units,
            'active_unit_id' => (int)($_SESSION['unit_active_unit_id'] ?? ($units[0]['id'] ?? 0)),
            'dob_proof_types' => Athlete::getDobProofTypes(),
            'flash'     => $this->flash(),
            'old'       => $_SESSION['old']    ?? [],
            'errors'    => $_SESSION['errors'] ?? [],
        ]);
        unset($_SESSION['old'], $_SESSION['errors']);
    }

    /**
     * POST /unit/athletes — create the managed athlete, dedupe by Aadhaar
     * / mobile / email, create a draft event_registration tied to the
     * picked unit, and redirect into the existing read-only view so the
     * Unit User can confirm and edit further from the dashboard.
     */
    public function storeAthlete(): void
    {
        $this->boot();
        $this->verifyCsrf();
        // Self-heal the schema — the Unit-User journey is the only one
        // that hits storeAthlete, so make sure both the unit-registration
        // migration AND the broader event-registration column set (run by
        // EventController on the admin side) are in place. Without this
        // a fresh install can crash on event_registrations.unit_id or
        // athletes.created_by_unit_id missing.
        try { Schema::ensureUnitRegistration(); } catch (\Throwable $e) {
            error_log('[unit/storeAthlete:ensureUnitRegistration] ' . $e->getMessage());
        }
        try { Schema::ensureSportHierarchy();   } catch (\Throwable $e) {
            error_log('[unit/storeAthlete:ensureSportHierarchy] ' . $e->getMessage());
        }
        try { Schema::ensureAthleteDobProof();   } catch (\Throwable $e) {
            error_log('[unit/storeAthlete:ensureAthleteDobProof] ' . $e->getMessage());
        }
        if (empty($this->event['allow_unit_registration'])) {
            $this->redirect('/unit/dashboard',
                'Unit-driven registration is not enabled for this event.', 'error');
        }

        $assigned = $this->assignedUnitIds();
        $unitId   = (int)($_POST['unit_id'] ?? 0);
        if (!in_array($unitId, $assigned, true)) {
            $this->redirect('/unit/athletes/new',
                'Pick one of the Units assigned to your account.', 'error');
        }

        $name    = trim((string)($_POST['name']          ?? ''));
        $gender  = strtolower(trim((string)($_POST['gender'] ?? '')));
        $dob     = trim((string)($_POST['date_of_birth'] ?? ''));
        $mobile  = trim((string)($_POST['mobile']        ?? ''));
        $email   = strtolower(trim((string)($_POST['email'] ?? '')));
        $aadhaar = preg_replace('/\s+/', '', (string)($_POST['id_proof_number'] ?? ''));
        $address = trim((string)($_POST['address']       ?? ''));
        $pwd     = strtolower(trim((string)($_POST['pwd_status'] ?? 'no')));
        if (!in_array($pwd, ['no', 'deaf', 'para'], true)) $pwd = 'no';

        // DOB proof (alternate proof of birth date when Aadhaar lacks DOB).
        $dobProofTypeId  = (int)($_POST['dob_proof_type_id'] ?? 0);
        $dobProofNumber  = trim((string)($_POST['dob_proof_number'] ?? ''));
        $allowedDobTypes = array_map('intval', array_column(Athlete::getDobProofTypes(), 'id'));
        if ($dobProofTypeId > 0 && !in_array($dobProofTypeId, $allowedDobTypes, true)) {
            $dobProofTypeId = 0;
        }

        $errors = [];
        // Per-event proof requirements: optional / mandatory / hide.
        $aadhaarReq        = $this->event['aadhaar_required']   ?? 'optional';
        $aadhaarMandatory  = $aadhaarReq === 'mandatory';
        $aadhaarHide       = $aadhaarReq === 'hide';
        $dobProofReq       = $this->event['dob_proof_required'] ?? 'optional';
        $dobProofMandatory = $dobProofReq === 'mandatory';
        $dobProofHide      = $dobProofReq === 'hide';
        // Hidden proofs are never collected — drop any posted values so they
        // can't sneak in.
        if ($aadhaarHide)  { $aadhaar = ''; }
        if ($dobProofHide) { $dobProofTypeId = 0; $dobProofNumber = ''; }

        if ($name === '')                                  $errors['name']         = 'Full name is required.';
        if (!in_array($gender, ['male', 'female', 'other'], true)) $errors['gender'] = 'Pick the athlete\'s gender.';
        if ($dob === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) $errors['date_of_birth'] = 'Enter a valid date of birth.';
        if ($mobile !== '' && !preg_match('/^[6-9]\d{9}$/', $mobile)) $errors['mobile'] = 'Enter a valid 10-digit mobile number.';
        if ($email   !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email or leave blank.';
        if (!$aadhaarHide) {
            if ($aadhaarMandatory) {
                if ($aadhaar === '' || !preg_match('/^\d{12}$/', $aadhaar)) {
                    $errors['id_proof_number'] = 'A 12-digit Aadhaar number is required for this event.';
                }
                // The file is required only when no proof_file is already in
                // play. We treat UPLOAD_ERR_NO_FILE as "missing", anything
                // else as "supplied" (the upload step below will surface
                // partial / oversize errors).
                $fileErr = (int)($_FILES['id_proof_file']['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($fileErr === UPLOAD_ERR_NO_FILE || empty($_FILES['id_proof_file']['name'])) {
                    $errors['id_proof_file'] = 'Aadhaar proof file is required for this event.';
                }
            } else {
                if ($aadhaar !== '' && !preg_match('/^\d{12}$/', $aadhaar)) {
                    $errors['id_proof_number'] = 'Aadhaar must be 12 digits or leave blank.';
                }
            }
        }
        // DOB proof — when mandatory the type, number and file are all required.
        if (!$dobProofHide && $dobProofMandatory) {
            if ($dobProofTypeId <= 0) {
                $errors['dob_proof_type_id'] = 'A Date of Birth proof type is required for this event.';
            }
            if ($dobProofNumber === '') {
                $errors['dob_proof_number'] = 'A Date of Birth proof document number is required for this event.';
            }
            $dobFileErr = (int)($_FILES['dob_proof_file']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($dobFileErr === UPLOAD_ERR_NO_FILE || empty($_FILES['dob_proof_file']['name'])) {
                $errors['dob_proof_file'] = 'A Date of Birth proof file is required for this event.';
            }
        }

        // Per-field dedupe — surface these as INLINE errors next to the
        // offending field so the Unit User can fix the form in place
        // (a flash banner alone was too easy to miss).
        if (!$errors) {
            if ($aadhaar !== '') {
                $hit = Athlete::findExistingForUnitDedupe($aadhaar, null, null);
                if ($hit) {
                    $errors['id_proof_number'] = 'An athlete with this Aadhaar already exists in the system'
                        . ' (' . (string)$hit['name'] . '). Contact the event administrator to link them to your Unit.';
                }
            }
            if (!isset($errors['mobile']) && $mobile !== '') {
                $hit = Athlete::findExistingForUnitDedupe(null, $mobile, null);
                if ($hit) {
                    $errors['mobile'] = 'An athlete with this mobile number already exists'
                        . ' (' . (string)$hit['name'] . ').';
                }
            }
            if (!isset($errors['email']) && $email !== '') {
                $hit = Athlete::findExistingForUnitDedupe(null, null, $email);
                if ($hit) {
                    $errors['email'] = 'An athlete with this email already exists'
                        . ' (' . (string)$hit['name'] . ').';
                } else {
                    // Also catch the case where a stub user (non-athlete
                    // or partially-set-up athlete) already owns the email.
                    $existingUser = \Models\User::findByEmail($email);
                    if ($existingUser) {
                        $errors['email'] = 'A user with this email already exists. '
                            . 'Leave the email blank to create a managed athlete.';
                    }
                }
            }
        }

        if ($errors) {
            $_SESSION['old']    = $_POST;
            $_SESSION['errors'] = $errors;
            $this->redirect('/unit/athletes/new', 'Fix the highlighted fields.', 'error');
        }

        // Optional photo / Aadhaar file uploads. Catch \Throwable (not
        // just \RuntimeException) so any underlying FileUpload failure
        // — bad mime, permissions, ImageMagick missing — surfaces as a
        // flash instead of a blank 500.
        $passportPhoto = null;
        $idProofFile   = null;
        if (!empty($_FILES['passport_photo']['name'])) {
            try { $passportPhoto = (new FileUpload())->upload($_FILES['passport_photo'], 'athletes/photos', true); }
            catch (\Throwable $e) {
                error_log('[unit/storeAthlete:passport_photo] ' . $e->getMessage());
                $_SESSION['old'] = $_POST;
                $this->redirect('/unit/athletes/new', 'Photo upload failed: ' . $e->getMessage(), 'error');
            }
        }
        if (!empty($_FILES['id_proof_file']['name'])) {
            try { $idProofFile = (new FileUpload())->upload($_FILES['id_proof_file'], 'athletes/idproofs'); }
            catch (\Throwable $e) {
                error_log('[unit/storeAthlete:id_proof_file] ' . $e->getMessage());
                $_SESSION['old'] = $_POST;
                $this->redirect('/unit/athletes/new', 'Aadhaar proof upload failed: ' . $e->getMessage(), 'error');
            }
        }
        $dobProofFile = null;
        if (!empty($_FILES['dob_proof_file']['name'])) {
            try { $dobProofFile = (new FileUpload())->upload($_FILES['dob_proof_file'], 'athletes/idproofs'); }
            catch (\Throwable $e) {
                error_log('[unit/storeAthlete:dob_proof_file] ' . $e->getMessage());
                $_SESSION['old'] = $_POST;
                $this->redirect('/unit/athletes/new', 'DOB proof upload failed: ' . $e->getMessage(), 'error');
            }
        }

        // Persist in a single try/catch so any unexpected DB error
        // (missing column, FK violation, etc.) lands as a flash on the
        // form instead of a blank 500.
        try {
            // Stub user row only when an email was supplied. The password
            // is a random secret the athlete will reset via "Forgot
            // password" when they claim the account.
            $userId = null;
            if ($email !== '') {
                $userId = \Models\User::create($email,
                    password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 'athlete');
            }

            $athleteId = Athlete::createManaged([
                'name'              => mb_substr($name, 0, 255),
                'gender'            => $gender,
                'date_of_birth'     => $dob,
                'mobile'            => $mobile ?: null,
                'address'           => $address ?: null,
                'id_proof_number'   => $aadhaar ?: null,
                'id_proof_file'     => $idProofFile,
                'passport_photo'    => $passportPhoto,
                'pwd_status'        => $pwd,
                'dob_proof_type_id' => $dobProofTypeId ?: null,
                'dob_proof_number'  => $dobProofNumber ?: null,
                'dob_proof_file'    => $dobProofFile,
                'profile_completed' => 1,
            ], $userId, $unitId);

            // Create the draft event_registration pinned to this unit.
            $regId = EventRegistration::createDraft((int)$this->event['id'], $athleteId);
            EventRegistration::updateHeader($regId, ['unit_id' => $unitId]);
        } catch (\Throwable $e) {
            error_log('[unit/storeAthlete] ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $_SESSION['old'] = $_POST;
            $this->redirect('/unit/athletes/new',
                'Could not create the athlete: ' . $e->getMessage(), 'error');
        }

        $this->redirect('/unit/athletes/' . \hid_reg($regId),
            'Athlete created. Open the registration to add sport-events and submit.');
    }

    // ── NOC management ───────────────────────────────────────────────────────

    /** Resolve the active unit from ?unit_id / session / first assigned. */
    private function pickActiveUnit(array $units): ?array
    {
        if (!$units) return null;
        $requested = (int)($_GET['unit_id'] ?? ($_SESSION['unit_active_unit_id'] ?? 0));
        foreach ($units as $u) {
            if ((int)$u['id'] === $requested) return $u;
        }
        return $units[0];
    }

    /** GET /unit/noc — NOC management screen. */
    public function nocIndex(): void
    {
        $this->boot();
        $units  = $this->assignedUnits();
        $active = $this->pickActiveUnit($units);
        if ($active) $_SESSION['unit_active_unit_id'] = (int)$active['id'];
        $athletes = $active
            ? Noc::athletesForUnit((int)$this->event['id'], (int)$active['id'])
            : [];
        $this->renderWith('unit', 'unit/noc', [
            'unit_user'   => $this->unitUser,
            'event'       => $this->event,
            'units'       => $units,
            'active_unit' => $active,
            'athletes'    => $athletes,
            'flash'       => $this->flash(),
        ]);
    }

    /** POST /unit/noc/set — AJAX update of one athlete's NOC status. */
    public function nocSet(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $regId  = (int)($_POST['registration_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, Noc::STATUSES, true)) {
            $this->json(['success' => false, 'message' => 'Invalid NOC status.']);
        }
        $reg = EventRegistration::findById($regId);
        $allowed = $this->assignedUnitIds();
        if (!$reg
            || (int)$reg['event_id'] !== (int)$this->event['id']
            || !in_array((int)($reg['unit_id'] ?? 0), $allowed, true)
            || ($reg['admin_review_status'] ?? '') !== 'approved') {
            $this->json(['success' => false, 'message' => 'Athlete not found in your unit.']);
        }
        Noc::setStatus($regId, $status, (string)$this->unitUser['name']);
        $this->json([
            'success' => true,
            'message' => 'NOC status updated.',
            'status'  => $status,
            'at'      => date('d M Y H:i'),
            'by'      => $this->unitUser['name'],
        ]);
    }

    /** GET /unit/noc/print — print-ready NOC report (honours filters). */
    public function nocPrint(): void
    {
        $this->boot();
        $units  = $this->assignedUnits();
        $active = $this->pickActiveUnit($units);
        $athletes = $active
            ? Noc::athletesForUnit((int)$this->event['id'], (int)$active['id'])
            : [];
        $fStatus = (string)($_GET['status'] ?? '');
        $fName   = trim((string)($_GET['name'] ?? ''));
        if (in_array($fStatus, Noc::STATUSES, true)) {
            $athletes = array_filter($athletes, fn($a) => $a['noc_status'] === $fStatus);
        }
        if ($fName !== '') {
            $athletes = array_filter($athletes, fn($a) => stripos((string)$a['athlete_name'], $fName) !== false);
        }
        $this->renderWith('print', 'unit/noc-print', [
            'event'         => $this->event,
            'active_unit'   => $active,
            'athletes'      => array_values($athletes),
            'filter_status' => $fStatus,
            'filter_name'   => $fName,
        ]);
    }

    /**
     * POST /unit/unit-logo — upload a (square-cropped) logo for one of the
     * unit user's assigned units, from the dashboard Unit Details panel.
     */
    public function uploadUnitLogo(): void
    {
        $this->boot();
        $this->verifyCsrf();
        $unitId  = (int)($_POST['unit_id'] ?? 0);
        $allowed = $this->assignedUnitIds();
        if (!$unitId || !in_array($unitId, $allowed, true)) {
            $this->json(['success' => false, 'message' => 'You are not permitted to manage this unit.']);
        }
        $unit = EventUnit::find($unitId);
        if (!$unit || (int)$unit['event_id'] !== (int)$this->event['id']) {
            $this->json(['success' => false, 'message' => 'Unit not found for this event.']);
        }
        if (empty($_FILES['logo']) || empty($_FILES['logo']['name'])) {
            $this->json(['success' => false, 'message' => 'No logo image received.']);
        }
        try {
            $url = (new FileUpload())->upload($_FILES['logo'], 'units', true);
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()]);
        }
        EventUnit::updateRow($unitId, ['logo' => $url]);
        $this->json(['success' => true, 'message' => 'Unit logo updated.', 'logo_url' => $url]);
    }

    // ── Registrations menu ──────────────────────────────────────────────────

    /**
     * GET /unit/registrations — every athlete the Unit User has registered
     * on this event (across all assigned units), with the running demand /
     * claimed amounts + submission state. Same data the per-athlete view
     * shows, just rolled up.
     */
    public function registrationsList(): void
    {
        $this->boot();
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {}
        $unitIds = $this->assignedUnitIds();
        $rows = $unitIds ? $this->registrationsAcrossUnits($unitIds) : [];
        $this->renderWith('unit', 'unit/registrations', [
            'unit_user'     => $this->unitUser,
            'event'         => $this->event,
            'registrations' => $rows,
            'flash'         => $this->flash(),
        ]);
    }

    /**
     * POST /unit/registrations/bulk-pay — single bank transaction
     * covering N selected athletes. Creates one event_registration_
     * payments row per selected registration, all sharing the same
     * date / number / proof file. Each row's amount is that
     * registration's outstanding balance (demand minus already-claimed
     * non-rejected payments). The total displayed on the modal is the
     * sum of those balances — server-side we re-derive it, so the
     * client can't fake the amount.
     */
    public function bulkPayRegistrations(): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {}

        $ids = $_POST['registration_ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            $this->redirect('/unit/registrations',
                'Select at least one registration before bulk-paying.', 'warning');
        }
        $txDate = trim((string)($_POST['transaction_date']   ?? ''));
        $txNum  = trim((string)($_POST['transaction_number'] ?? ''));
        if ($txDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $txDate)) {
            $this->redirect('/unit/registrations', 'Enter a valid transaction date.', 'error');
        }
        if ($txNum === '') {
            $this->redirect('/unit/registrations', 'Transaction number is required.', 'error');
        }
        if (empty($_FILES['transaction_proof']['name'])) {
            $this->redirect('/unit/registrations', 'Attach the transaction proof file.', 'error');
        }

        $allowed = $this->assignedUnitIds();
        $created = 0; $skipped = 0; $total = 0.0;
        try {
            // Upload the proof ONCE — every created row reuses the URL.
            $proof = (new FileUpload())->upload($_FILES['transaction_proof'], 'registrations');
            foreach ($ids as $rid) {
                $reg = EventRegistration::withProfile($rid);
                if (!$reg
                    || (int)$reg['event_id'] !== (int)$this->event['id']
                    || empty($reg['unit_id'])
                    || !in_array((int)$reg['unit_id'], $allowed, true)
                    || !EventRegistration::isEditable($reg)
                ) { $skipped++; continue; }

                $demand  = (float)EventRegistration::sumFee($rid);
                $claimed = (float)EventRegistrationPayment::sumClaimed($rid);
                $balance = round($demand - $claimed, 2);
                if ($balance <= 0) { $skipped++; continue; }

                EventRegistrationPayment::create([
                    'registration_id'    => $rid,
                    'event_id'           => (int)$this->event['id'],
                    'transaction_date'   => $txDate,
                    'transaction_number' => $txNum,
                    'amount'             => $balance,
                    'proof_file'         => $proof,
                    'payment_method'     => 'manual',
                    'status'             => 'pending',
                ]);
                EventRegistration::updateHeader($rid, ['payment_mode' => 'manual']);
                EventRegistrationPayment::recomputeRegistrationPaymentStatus($rid);
                $created++;
                $total += $balance;
            }
        } catch (\Throwable $e) {
            error_log('[unit/bulkPayRegistrations] ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->redirect('/unit/registrations',
                'Bulk save failed: ' . $e->getMessage(), 'error');
        }
        $msg = sprintf(
            'Bulk transaction logged: %d row%s created (₹%s)%s.',
            $created, $created === 1 ? '' : 's',
            number_format($total, 2),
            $skipped > 0 ? ", {$skipped} skipped (no balance / locked)" : ''
        );
        $this->redirect('/unit/registrations', $msg, $created > 0 ? 'success' : 'warning');
    }

    /**
     * POST /unit/registrations/bulk-submit — submit several draft /
     * returned registrations to the event administrator in one go. Each
     * registration is held to the same gate as the single-submit flow:
     * it must be editable, carry at least one sport-event, and have its
     * transactions fully cover the demand. Ineligible rows are skipped
     * (with a count back to the operator) rather than failing the batch.
     */
    public function bulkSubmitRegistrations(): void
    {
        $this->boot();
        $this->verifyCsrf();
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {}

        $ids = $_POST['registration_ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            $this->redirect('/unit/registrations',
                'Select at least one registration before submitting.', 'warning');
        }

        $allowed   = $this->assignedUnitIds();
        $submitted = 0; $skipped = 0;
        $epsilon   = 0.005;
        foreach ($ids as $rid) {
            $reg = EventRegistration::withProfile($rid);
            if (!$reg
                || (int)$reg['event_id'] !== (int)$this->event['id']
                || empty($reg['unit_id'])
                || !in_array((int)$reg['unit_id'], $allowed, true)
                || !EventRegistration::isEditable($reg)
            ) { $skipped++; continue; }

            // Must have at least one sport-event and be fully paid against
            // the demand (pending + approved counts as claimed).
            if (!EventRegistration::items($rid)) { $skipped++; continue; }
            $demand  = (float)EventRegistration::sumFee($rid);
            $claimed = (float)EventRegistrationPayment::sumClaimed($rid);
            if ($demand <= 0 || abs($demand - $claimed) > $epsilon) { $skipped++; continue; }

            EventRegistration::updateHeader($rid, [
                'status'              => 'pending',
                'admin_review_status' => 'pending',
                'submitted_at'        => date('Y-m-d H:i:s'),
            ]);
            $submitted++;
        }

        $msg = sprintf(
            '%d registration%s submitted for review%s.',
            $submitted, $submitted === 1 ? '' : 's',
            $skipped > 0 ? ", {$skipped} skipped (locked, no events, or balance not settled)" : ''
        );
        $this->redirect('/unit/registrations', $msg, $submitted > 0 ? 'success' : 'warning');
    }

    /**
     * GET /unit/transactions — read-only ledger of every payment row
     * across the Unit User's registrations. To log a new manual
     * transaction, the operator goes to /unit/registrations and uses
     * the Bulk Payment Transaction modal (or opens an individual
     * registration and adds it there).
     */
    public function transactionsList(): void
    {
        $this->boot();
        try { Schema::ensureSportHierarchy(); } catch (\Throwable $e) {}
        $unitIds = $this->assignedUnitIds();
        $rows    = $unitIds ? $this->paymentsAcrossUnits($unitIds) : [];
        $this->renderWith('unit', 'unit/transactions', [
            'unit_user'    => $this->unitUser,
            'event'        => $this->event,
            'transactions' => $rows,
            'flash'        => $this->flash(),
        ]);
    }

    // ── Internal helpers ─────────────────────────────────────────────────────

    private function registrationsAcrossUnits(array $unitIds): array
    {
        $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
        $params = array_merge([(int)$this->event['id']], array_map('intval', $unitIds));
        return Event::rowsRaw(
            "SELECT er.id, er.admin_review_status, er.payment_status,
                    er.submitted_at, er.total_amount, er.unit_id,
                    a.name AS athlete_name, a.gender, a.date_of_birth,
                    eu.name AS unit_name,
                    (SELECT COUNT(*) FROM event_registration_items eri
                       WHERE eri.registration_id = er.id) AS items_count,
                    (SELECT COALESCE(SUM(p.amount), 0)
                       FROM event_registration_payments p
                      WHERE p.registration_id = er.id
                        AND COALESCE(p.payment_method,'manual') <> 'demand'
                        AND p.status <> 'rejected') AS claimed_amount,
                    (SELECT COALESCE(SUM(p.amount), 0)
                       FROM event_registration_payments p
                      WHERE p.registration_id = er.id
                        AND COALESCE(p.payment_method,'manual') <> 'demand'
                        AND p.status = 'approved') AS approved_amount
               FROM event_registrations er
               JOIN athletes   a   ON a.id = er.athlete_id
          LEFT JOIN event_units eu ON eu.id = er.unit_id
              WHERE er.event_id = ? AND er.unit_id IN ({$placeholders})
              ORDER BY eu.name, a.name",
            $params
        );
    }

    private function paymentsAcrossUnits(array $unitIds): array
    {
        $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
        $params = array_merge([(int)$this->event['id']], array_map('intval', $unitIds));
        return Event::rowsRaw(
            "SELECT p.*, er.athlete_id, er.unit_id,
                    a.name AS athlete_name,
                    eu.name AS unit_name
               FROM event_registration_payments p
               JOIN event_registrations er ON er.id = p.registration_id
               JOIN athletes   a   ON a.id = er.athlete_id
          LEFT JOIN event_units eu ON eu.id = er.unit_id
              WHERE er.event_id = ? AND er.unit_id IN ({$placeholders})
                AND COALESCE(p.payment_method,'manual') <> 'demand'
              ORDER BY p.transaction_date DESC, p.id DESC",
            $params
        );
    }

    private function statsForUnit(int $unitId): array
    {
        $r = Event::rowsRaw(
            "SELECT
                COUNT(*) AS total,
                COUNT(CASE WHEN admin_review_status = 'approved' THEN 1 END) AS approved,
                COALESCE(SUM(total_amount), 0) AS demand,
                COALESCE((
                    SELECT SUM(p.amount)
                      FROM event_registration_payments p
                      JOIN event_registrations er2 ON er2.id = p.registration_id
                     WHERE er2.event_id = ? AND er2.unit_id = ?
                       AND COALESCE(p.payment_method,'manual') <> 'demand'
                       AND p.status <> 'rejected'
                ), 0) AS claimed
               FROM event_registrations
              WHERE event_id = ? AND unit_id = ?",
            [(int)$this->event['id'], $unitId, (int)$this->event['id'], $unitId]
        );
        return [
            'total'    => (int)($r[0]['total']    ?? 0),
            'approved' => (int)($r[0]['approved'] ?? 0),
            'demand'   => (float)($r[0]['demand']  ?? 0),
            'claimed'  => (float)($r[0]['claimed'] ?? 0),
        ];
    }

    /**
     * Sport-event × gender pivot for the active unit. One row per
     * sport_event the unit has at least one registration on, columns
     * = male / female / mixed counts. Each row also carries the
     * event's own gender (the catalog row is fixed) so the view can
     * highlight the column that actually accrues counts.
     */
    private function sportEventPivotForUnit(int $unitId): array
    {
        return Event::rowsRaw(
            "SELECT es.id AS event_sport_id,
                    es.event_code,
                    COALESCE(se.name, es.category) AS event_name,
                    s.name AS sport_name,
                    sc.name AS sport_event_category,
                    ac.name AS sport_event_age_category,
                    se.gender AS sport_event_gender,
                    COUNT(DISTINCT eri.registration_id) AS total_count,
                    COUNT(DISTINCT CASE WHEN a.gender = 'male'   THEN eri.registration_id END) AS male_count,
                    COUNT(DISTINCT CASE WHEN a.gender = 'female' THEN eri.registration_id END) AS female_count,
                    COUNT(DISTINCT CASE WHEN a.gender = 'other'  THEN eri.registration_id END) AS other_count,
                    COALESCE(SUM(eri.fee), 0) AS demand
               FROM event_sports es
               JOIN sports               s   ON s.id  = es.sport_id
          LEFT JOIN sport_events        se   ON se.id = es.sport_event_id
          LEFT JOIN sport_categories    sc   ON sc.id = se.category_id
          LEFT JOIN age_categories      ac   ON ac.id = se.age_category_id
               JOIN event_registration_items eri ON eri.event_sport_id = es.id
               JOIN event_registrations  er  ON er.id  = eri.registration_id
               JOIN athletes             a   ON a.id  = er.athlete_id
              WHERE es.event_id = ? AND er.unit_id = ?
              GROUP BY es.id, es.event_code, sport_name, sport_event_category,
                       sport_event_age_category, event_name, se.gender
              ORDER BY sport_name, event_name",
            [(int)$this->event['id'], $unitId]
        );
    }

}
