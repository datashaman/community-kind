import { Form, Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { show, store } from '@/routes/volunteers';

type Opportunity = {
    id: string;
    title: string;
    status: string;
    capacity: number;
    applicationsCount: number;
};
const list = (value: unknown) =>
    typeof value === 'string'
        ? value
              .split(',')
              .map((item) => item.trim())
              .filter(Boolean)
        : [];

export default function VolunteersIndex({
    opportunities,
}: {
    opportunities: Opportunity[];
}) {
    const organisation = usePage().props.currentOrganisation!;
    return (
        <div className="space-y-8 p-4">
            <Head title="Volunteering" />
            <Heading
                title="Volunteering"
                description="Publish opportunities and manage the registration-to-hours journey."
            />
            <Card>
                <CardHeader>
                    <CardTitle>New opportunity</CardTitle>
                </CardHeader>
                <CardContent>
                    <Form
                        {...store.form(organisation.slug)}
                        transform={(data) => ({
                            ...data,
                            interest_tags: list(data.interest_tags),
                        })}
                        className="grid gap-4 md:grid-cols-2"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div>
                                    <Label htmlFor="title">Title</Label>
                                    <Input id="title" name="title" required />
                                    <InputError message={errors.title} />
                                </div>
                                <div>
                                    <Label htmlFor="capacity">Capacity</Label>
                                    <Input
                                        id="capacity"
                                        name="capacity"
                                        type="number"
                                        min="1"
                                        required
                                    />
                                </div>
                                <div className="md:col-span-2">
                                    <Label htmlFor="summary">Summary</Label>
                                    <Textarea
                                        id="summary"
                                        name="summary"
                                        required
                                    />
                                </div>
                                <div className="md:col-span-2">
                                    <Label htmlFor="interest_tags">
                                        Interest tags (comma separated)
                                    </Label>
                                    <Input
                                        id="interest_tags"
                                        name="interest_tags"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="registration_opens_at">
                                        Registration opens
                                    </Label>
                                    <Input
                                        id="registration_opens_at"
                                        name="registration_opens_at"
                                        type="datetime-local"
                                        required
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="registration_closes_at">
                                        Registration closes
                                    </Label>
                                    <Input
                                        id="registration_closes_at"
                                        name="registration_closes_at"
                                        type="datetime-local"
                                        required
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="starts_at">
                                        Opportunity starts
                                    </Label>
                                    <Input
                                        id="starts_at"
                                        name="starts_at"
                                        type="datetime-local"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="ends_at">
                                        Opportunity ends
                                    </Label>
                                    <Input
                                        id="ends_at"
                                        name="ends_at"
                                        type="datetime-local"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="status">Publication</Label>
                                    <select
                                        id="status"
                                        name="status"
                                        className="h-9 w-full rounded-md border bg-transparent px-3"
                                    >
                                        <option value="draft">Draft</option>
                                        <option value="published">
                                            Published
                                        </option>
                                    </select>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="self-end"
                                >
                                    Create opportunity
                                </Button>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>
            <div className="grid gap-4 md:grid-cols-2">
                {opportunities.map((opportunity) => (
                    <Card key={opportunity.id}>
                        <CardHeader>
                            <CardTitle>
                                <Link
                                    href={show([
                                        organisation.slug,
                                        opportunity.id,
                                    ])}
                                >
                                    {opportunity.title}
                                </Link>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex gap-2">
                                <Badge>{opportunity.status}</Badge>
                                <Badge variant="outline">
                                    {opportunity.applicationsCount} /{' '}
                                    {opportunity.capacity} applications
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </div>
    );
}

VolunteersIndex.layout = () => ({
    breadcrumbs: [{ title: 'Volunteering', href: '#' }],
});
