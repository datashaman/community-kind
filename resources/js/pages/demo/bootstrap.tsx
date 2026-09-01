import { Form, Head } from '@inertiajs/react';
import { FlaskConical, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { store } from '@/routes/demo/bootstrap';

type Props = {
    token: string;
    expiresAt: string;
    expiresAtLabel: string;
};

export default function DemoBootstrap({
    token,
    expiresAt,
    expiresAtLabel,
}: Props) {
    return (
        <main className="bg-muted/30 flex min-h-screen items-center px-4 py-12">
            <Head title="Enter the CommunityKind demo" />
            <Card className="mx-auto w-full max-w-xl">
                <CardHeader className="space-y-4 text-center">
                    <FlaskConical
                        className="mx-auto size-10 text-amber-600"
                        aria-hidden="true"
                    />
                    <div className="space-y-2">
                        <CardTitle className="text-2xl">
                            Explore a synthetic CommunityKind workspace
                        </CardTitle>
                        <CardDescription className="text-base">
                            Choose from several staff perspectives and explore
                            realistic, fictional community-service data.
                        </CardDescription>
                    </div>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="bg-muted flex gap-3 rounded-lg p-4 text-sm">
                        <ShieldCheck
                            className="mt-0.5 size-5 shrink-0 text-emerald-700"
                            aria-hidden="true"
                        />
                        <p>
                            This isolated demo cannot reach real organisations,
                            send messages, accept payments, or upload files. It
                            expires{' '}
                            <time dateTime={expiresAt}>{expiresAtLabel}</time>.
                        </p>
                    </div>
                    <Form {...store.form({ token })}>
                        {({ processing }) => (
                            <Button
                                className="w-full"
                                size="lg"
                                type="submit"
                                disabled={processing}
                            >
                                {processing
                                    ? 'Preparing perspectives…'
                                    : 'Enter the demo'}
                            </Button>
                        )}
                    </Form>
                    <p className="text-muted-foreground text-center text-xs">
                        Opening this page does not consume the access link.
                    </p>
                </CardContent>
            </Card>
        </main>
    );
}
