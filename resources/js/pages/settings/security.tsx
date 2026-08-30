import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import { acknowledge } from '@/routes/security/recovery-codes';
import { destroy as destroyOtherBrowserSessions } from '@/routes/security/other-browser-sessions';

// oxfmt-ignore
type Props = {
    passwordRules: string;
    recoveryCodesAcknowledged?: boolean;
    required?: 'mfa' | 'recovery-codes' | null;
    otherBrowserSessionCount: number;
} & ManageTwoFactorProps;

export default function Security(props: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Security settings" />

            <h1 className="sr-only">Security settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Update password"
                    description="Ensure your account is using a long, random password to stay secure"
                />

                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">
                                    Current password
                                </Label>

                                <PasswordInput
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    className="mt-1 block w-full"
                                    autoComplete="current-password"
                                    placeholder="Current password"
                                />

                                <InputError message={errors.current_password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">New password</Label>

                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="New password"
                                    passwordrules={props.passwordRules}
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="Confirm password"
                                    passwordrules={props.passwordRules}
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <ManageTwoFactor
                canManageTwoFactor={props.canManageTwoFactor}
                requiresConfirmation={props.requiresConfirmation}
                twoFactorEnabled={props.twoFactorEnabled}
            />

            {props.twoFactorEnabled && !props.recoveryCodesAcknowledged && (
                <div className="space-y-6 rounded-lg border p-6">
                    <Heading
                        variant="small"
                        title="Save your recovery codes"
                        description="Store the recovery codes above somewhere safe, then acknowledge that you can recover your account. Staff features remain locked until this is complete."
                    />

                    <Form {...acknowledge.form()}>
                        {({ processing, errors }) => (
                            <div className="space-y-4">
                                <label className="flex items-start gap-3 text-sm">
                                    <input
                                        type="checkbox"
                                        name="acknowledged"
                                        value="1"
                                        className="mt-1 size-4"
                                        required
                                    />
                                    <span>
                                        I have saved my recovery codes in a
                                        secure location.
                                    </span>
                                </label>
                                <InputError message={errors.acknowledged} />
                                <Button disabled={processing}>
                                    Acknowledge recovery codes
                                </Button>
                            </div>
                        )}
                    </Form>
                </div>
            )}

            {props.recoveryCodesAcknowledged && (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Browser sessions"
                        description={`${props.otherBrowserSessionCount} other active browser ${props.otherBrowserSessionCount === 1 ? 'session' : 'sessions'} found`}
                    />

                    <Form {...destroyOtherBrowserSessions.form()}>
                        {({ processing }) => (
                            <Button
                                variant="destructive"
                                disabled={
                                    processing ||
                                    props.otherBrowserSessionCount === 0
                                }
                            >
                                Revoke other browser sessions
                            </Button>
                        )}
                    </Form>
                </div>
            )}
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Security settings',
            href: edit(),
        },
    ],
};
