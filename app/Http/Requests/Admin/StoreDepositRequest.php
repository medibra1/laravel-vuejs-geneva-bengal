<?php

namespace App\Http\Requests\Admin;

use App\Rules\CatIsAvailableForDeposit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepositRequest extends FormRequest
{
    /**
     * Route middleware (role:admin|super_admin) already gates access to
     * this endpoint.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * new_owner.* is only required when new_owner itself is present — the
     * admin may leave both owner_id and new_owner empty (owner gets
     * attached later, at finalize() time) or pick an existing Owner
     * instead.
     *
     * name/email are required unless new_owner is present: when creating a
     * new owner inline, DepositController::store() derives the deposit's
     * contact fields from that owner instead — no point making the admin
     * type the same name/email twice (see Admin/Deposits/Form.vue). Linking
     * an *existing* owner still submits name/email as normal — the form
     * just pre-fills them read-only from the selected owner.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cat_id' => ['nullable', 'exists:cats,id', new CatIsAvailableForDeposit],
            'name' => ['required_without:new_owner', 'nullable', 'string', 'max:255'],
            'email' => ['required_without:new_owner', 'nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'amount' => ['nullable', 'integer', 'min:0'],
            // Stripe deliberately excluded here — an admin-recorded
            // reservation only ever offers cash/bank_transfer/twint_manual
            // now (see CLAUDE.md): the "payment link" it used to generate
            // only ever led to a status page, never a real payment form.
            // The public flow still uses PaymentMethod::Stripe as normal —
            // Rule::in() rather than Rule::enum(PaymentMethod::class) since
            // this deliberately excludes one of the enum's own cases.
            //
            // nullable (not required): the admin can leave the method "to
            // be defined later" and choose it when actually marking the
            // deposit paid instead — see
            // Admin\DepositController::markPaid() and
            // Admin\MarkDepositPaidRequest.
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'twint_manual'])],
            'owner_id' => ['nullable', 'exists:owners,id'],
            'new_owner' => ['nullable', 'array'],
            'new_owner.first_name' => ['required_with:new_owner', 'string', 'max:255'],
            'new_owner.last_name' => ['required_with:new_owner', 'string', 'max:255'],
            'new_owner.email' => ['required_with:new_owner', 'email', 'max:255', 'unique:owners,email'],
            'new_owner.phone' => ['nullable', 'string', 'max:50'],
            'new_owner.city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
