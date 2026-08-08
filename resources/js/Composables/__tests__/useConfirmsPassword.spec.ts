import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useConfirmsPassword } from '../useConfirmsPassword';

function jsonResponse(body: unknown, init: { ok?: boolean; status?: number } = {}): Response {
    return {
        ok: init.ok ?? true,
        status: init.status ?? 200,
        json: () => Promise.resolve(body),
    } as Response;
}

beforeEach(() => {
    globalThis.route = vi.fn((name: string) => `/${name}`) as unknown as typeof route;
    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
});

describe('useConfirmsPassword', () => {
    it('runs the callback immediately when the session is already confirmed', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue(jsonResponse({ confirmed: true }));
        const { confirmPassword, confirmingPassword } = useConfirmsPassword();
        const callback = vi.fn();

        await confirmPassword(callback);

        expect(fetch).toHaveBeenCalledWith('/password.confirm', { headers: { Accept: 'application/json' } });
        expect(callback).toHaveBeenCalledOnce();
        expect(confirmingPassword.value).toBe(false);
    });

    it('opens the modal instead of running the callback when confirmation is stale', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue(jsonResponse({ confirmed: false }));
        const { confirmPassword, confirmingPassword } = useConfirmsPassword();
        const callback = vi.fn();

        await confirmPassword(callback);

        expect(callback).not.toHaveBeenCalled();
        expect(confirmingPassword.value).toBe(true);
    });

    it('submits the password and runs the pending callback on success', async () => {
        globalThis.fetch = vi
            .fn()
            .mockResolvedValueOnce(jsonResponse({ confirmed: false }))
            .mockResolvedValueOnce(jsonResponse({ confirmed: true }));
        const { confirmPassword, submitPassword, confirmingPassword, form } = useConfirmsPassword();
        const callback = vi.fn();

        await confirmPassword(callback);
        form.password = 'secret';
        await submitPassword();

        expect(fetch).toHaveBeenLastCalledWith(
            '/password.confirm',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({ 'X-CSRF-TOKEN': 'test-token' }),
                body: JSON.stringify({ password: 'secret' }),
            }),
        );
        expect(callback).toHaveBeenCalledOnce();
        expect(confirmingPassword.value).toBe(false);
        expect(form.password).toBe('');
    });

    it('surfaces the validation error and keeps the modal open on an incorrect password', async () => {
        globalThis.fetch = vi
            .fn()
            .mockResolvedValueOnce(jsonResponse({ confirmed: false }))
            .mockResolvedValueOnce(jsonResponse({ errors: { password: ['Le mot de passe est incorrect.'] } }, { ok: false, status: 422 }));
        const { confirmPassword, submitPassword, confirmingPassword, form } = useConfirmsPassword();
        const callback = vi.fn();

        await confirmPassword(callback);
        await submitPassword();

        expect(callback).not.toHaveBeenCalled();
        expect(confirmingPassword.value).toBe(true);
        expect(form.error).toBe('Le mot de passe est incorrect.');
        expect(form.processing).toBe(false);
    });
});
