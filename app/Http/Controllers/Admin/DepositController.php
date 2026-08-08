<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CatStatus;
use App\Enums\CatType;
use App\Enums\DepositStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCatToDepositRequest;
use App\Http\Requests\Admin\FinalizeDepositRequest;
use App\Http\Requests\Admin\StoreDepositRequest;
use App\Models\Cat;
use App\Models\Deposit;
use App\Models\Owner;
use App\Models\SiteSetting;
use App\Services\Payments\DepositPaymentProcessor;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DepositController extends Controller
{
    public function index(): Response
    {
        $deposits = QueryBuilder::for(Deposit::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('cat_id'),
                // Matches CarbonPeriod-style from/to used by the dashboard's
                // own period filter — see DashboardController.
                AllowedFilter::callback(
                    'from',
                    fn ($query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()),
                ),
                AllowedFilter::callback(
                    'to',
                    fn ($query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()),
                ),
                // Waiting-list entries are deposits with no cat attached
                // (see CLAUDE.md: cat_id nullable = generic reservation) —
                // surfaced as its own nav destination, see AdminLayout.vue.
                AllowedFilter::callback(
                    'waiting_list',
                    fn ($query, $value) => $value ? $query->whereNull('cat_id') : $query,
                ),
            )
            ->with(['cat:id,name', 'owner:id,first_name,last_name'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Deposits/Index', [
            'deposits' => $deposits,
            'cats' => Cat::query()->orderBy('name')->get(['id', 'name']),
            // For the "finalize" dialog's existing-owner picker — only
            // needed when a deposit has no owner_id yet.
            'owners' => Owner::query()->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email', 'phone']),
            // For the "assign a cat" dialog on a waiting-list entry — unlike
            // `cats` above (a plain filter, any cat is a valid choice
            // there), this excludes breeders and already-reserved/adopted
            // cats, same as the create() form's own cat picker.
            'reservableCats' => $this->reservableCatOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Deposits/Form', [
            'cats' => $this->reservableCatOptions(),
            'owners' => Owner::query()->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email', 'phone']),
            'defaultAmount' => SiteSetting::get('deposit_amount', 50000),
        ]);
    }

    /**
     * The admin's counterpart to Public\DepositController::store() — same
     * Deposit shape, but amount/owner/payment method are all trusted admin
     * input instead of derived server-side, since this is staff recording
     * a reservation made in person/by phone, not a public form.
     */
    public function store(StoreDepositRequest $request, PaymentGateway $gateway, DepositPaymentProcessor $processor): RedirectResponse
    {
        $owner = $this->resolveOwner($request);

        [$name, $email, $phone] = $this->resolveContact($request, $owner);

        $deposit = Deposit::create([
            'cat_id' => $request->validated('cat_id'),
            'owner_id' => $owner?->id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'amount' => $request->validated('amount') ?? SiteSetting::get('deposit_amount', 50000),
            'currency' => 'CHF',
            'status' => DepositStatus::Pending->value,
            'payment_method' => $request->validated('payment_method'),
            // Mirrors payment_method for a manually-recorded deposit: there
            // is no separate PSP involved, so leaving the "stripe" DB
            // default here would misrepresent a cash/bank_transfer/
            // twint_manual deposit as having gone through Stripe.
            'provider' => $request->validated('payment_method'),
            'created_by' => $request->user()->id,
        ]);

        $processor->reserve($deposit);

        if ($deposit->payment_method === PaymentMethod::Stripe) {
            $checkout = $gateway->createCheckout($deposit);

            $deposit->update([
                'provider_reference' => $checkout->id,
                'payment_link_url' => $checkout->url,
            ]);
        }

        return redirect()->route('admin.deposits.index')->with('success', __('Deposit created.'));
    }

    /**
     * For cash/bank_transfer/twint_manual only — a Stripe deposit is only
     * ever marked paid by the webhook (see CLAUDE.md: it's the sole
     * source of truth there, a manual override would defeat that).
     */
    public function markPaid(Deposit $deposit, DepositPaymentProcessor $processor): RedirectResponse
    {
        if ($deposit->payment_method === PaymentMethod::Stripe) {
            return back()->with('error', __('Stripe deposits are marked paid automatically once the webhook confirms payment.'));
        }

        if ($deposit->status !== DepositStatus::Pending) {
            return back()->with('error', __('Only a pending deposit can be marked paid.'));
        }

        $processor->markPaid($deposit);

        return back()->with('success', __('Deposit marked as paid.'));
    }

    /**
     * "Finalize the adoption": requires a paid deposit, links/creates the
     * Owner (skipped if the deposit already has one — e.g. set back at
     * creation), and moves the reserved cat, if any, to `adopte`.
     */
    public function finalize(FinalizeDepositRequest $request, Deposit $deposit, DepositPaymentProcessor $processor): RedirectResponse
    {
        if ($deposit->status !== DepositStatus::Paid) {
            return back()->with('error', __('Only a paid deposit can be finalized.'));
        }

        if ($deposit->finalized_at !== null) {
            return back()->with('error', __('This deposit was already finalized.'));
        }

        $owner = $deposit->owner ?? $this->resolveOwner($request);

        if ($owner === null) {
            return back()->with('error', __('An owner is required to finalize this deposit.'));
        }

        $processor->finalize($deposit, $owner);

        return back()->with('success', __('Adoption finalized.'));
    }

    /**
     * Turns a waiting-list entry (cat_id null) into a reservation for a
     * specific kitten once one becomes available. Restricted to a still-
     * pending deposit: once it's paid, the deposit is tied to whatever the
     * family already paid a deposit for — reassigning the cat under it
     * afterwards would misrepresent what they actually paid for.
     */
    public function assignCat(AssignCatToDepositRequest $request, Deposit $deposit, DepositPaymentProcessor $processor): RedirectResponse
    {
        if ($deposit->cat_id !== null) {
            return back()->with('error', __('This reservation is already tied to a cat.'));
        }

        if ($deposit->status !== DepositStatus::Pending) {
            return back()->with('error', __('A cat can only be assigned to a still-pending waiting-list entry.'));
        }

        $deposit->update(['cat_id' => $request->validated('cat_id')]);
        $processor->reserve($deposit);

        return back()->with('success', __('Cat assigned to the reservation.'));
    }

    /**
     * super_admin only — see CLAUDE.md's role split. Route middleware
     * enforces this; the paid-status check here is a separate business
     * rule (nothing to refund on a deposit that was never charged).
     */
    public function refund(Deposit $deposit, PaymentGateway $gateway): RedirectResponse
    {
        if ($deposit->status !== DepositStatus::Paid) {
            return back()->with('error', __('Only a paid deposit can be refunded.'));
        }

        if (! $gateway->refund($deposit)) {
            return back()->with('error', __('The refund could not be processed.'));
        }

        $deposit->update(['status' => DepositStatus::Refunded]);

        return back()->with('success', __('Deposit refunded.'));
    }

    /**
     * When a new Owner was just created inline (Form.vue's "new owner"
     * mode), the deposit's name/email/phone are derived from it instead of
     * the request — the form doesn't ask the admin to type the same
     * contact details twice. Linking an *existing* owner still uses the
     * submitted name/email/phone as-is (the form pre-fills them, read-only,
     * from the selected owner — see StoreDepositRequest).
     *
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function resolveContact(StoreDepositRequest $request, ?Owner $owner): array
    {
        if ($request->filled('new_owner') && $owner !== null) {
            return [trim("{$owner->first_name} {$owner->last_name}"), $owner->email, $owner->phone];
        }

        return [$request->validated('name'), $request->validated('email'), $request->validated('phone')];
    }

    /**
     * owner_id wins if both are somehow submitted; null means "leave it
     * for finalize() to resolve later" (only valid from store()).
     */
    private function resolveOwner(FormRequest $request): ?Owner
    {
        $ownerId = $request->validated('owner_id');

        if ($ownerId !== null) {
            return Owner::find($ownerId);
        }

        if ($request->filled('new_owner')) {
            return Owner::create($request->validated('new_owner'));
        }

        return null;
    }

    /**
     * Adoption-type cats only (kittens/cats, not breeders — see
     * Admin\Cats\AdoptionCatController) that aren't already adopted.
     * Same "reject in PHP, not in SQL" approach as
     * Admin\OwnerController::adoptableCatOptions() — status lives in the
     * statuses table via spatie/laravel-model-status, not a plain column.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function reservableCatOptions(): array
    {
        return Cat::query()
            ->whereIn('type', [CatType::Kitten, CatType::Cat])
            ->with('statuses')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->reject(fn (Cat $cat) => $cat->status === CatStatus::Adopted->value)
            ->map(fn (Cat $cat) => ['id' => $cat->id, 'name' => $cat->name])
            ->values()
            ->all();
    }
}
