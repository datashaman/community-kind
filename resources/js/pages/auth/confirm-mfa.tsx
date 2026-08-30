import { Form, Head } from '@inertiajs/react';
import MfaChallengeController from '@/actions/App/Http/Controllers/Auth/MfaChallengeController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function ConfirmMfa() {
    return (
        <>
            <Head title="Confirm two-factor authentication" />

            <Form {...MfaChallengeController.store.form()} resetOnSuccess>
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="code">Authentication code</Label>
                            <Input
                                id="code"
                                name="code"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                autoFocus
                                required
                            />
                            <InputError message={errors.code} />
                        </div>

                        <Button className="w-full" disabled={processing}>
                            {processing && <Spinner />}
                            Confirm
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmMfa.layout = {
    title: 'Confirm two-factor authentication',
    description:
        'Enter a fresh code from your authenticator app to continue with this sensitive action.',
};
