<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DepositStatus;
use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepositController extends Controller
{
    public function index(): Response
    {
        $deposits = Deposit::query()
            ->with('cat:id,name')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Deposits/Index', [
            'deposits' => $deposits,
        ]);
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
}
