import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus, Users } from 'lucide-react';
import CreateOrganisationModal from '@/components/create-organisation-modal';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useIsMobile } from '@/hooks/use-mobile';
import { switchMethod } from '@/routes/organisations';
import type { Organisation } from '@/types';

type OrganisationSwitcherProps = {
    inHeader?: boolean;
};

export function OrganisationSwitcher({
    inHeader = false,
}: OrganisationSwitcherProps) {
    const page = usePage();
    const isMobile = useIsMobile();
    const currentOrganisation = page.props.currentOrganisation;
    const organisations = page.props.organisations ?? [];
    const canCreateOrganisation = page.props.canCreateOrganisation;

    const switchOrganisation = (organisation: Organisation) => {
        router.visit(switchMethod(organisation.slug), {
            preserveScroll: false,
            preserveState: false,
            replace: true,
        });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    data-test="organisation-switcher-trigger"
                    className={
                        inHeader
                            ? 'h-8 gap-1 px-2'
                            : 'data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground w-full justify-start px-2 has-[>svg]:px-2'
                    }
                >
                    <Users
                        className={
                            inHeader
                                ? 'hidden'
                                : 'hidden size-4 shrink-0 group-data-[collapsible=icon]:block'
                        }
                    />
                    <div
                        className={
                            inHeader
                                ? 'grid flex-1 text-left text-sm leading-tight'
                                : 'grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden'
                        }
                    >
                        <span
                            className={
                                inHeader
                                    ? 'max-w-[120px] truncate font-medium'
                                    : 'truncate font-semibold'
                            }
                        >
                            {currentOrganisation?.name ?? 'Select organisation'}
                        </span>
                    </div>
                    <ChevronsUpDown
                        className={
                            inHeader
                                ? 'size-4 opacity-50'
                                : 'ml-auto group-data-[collapsible=icon]:hidden'
                        }
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className={
                    inHeader
                        ? 'w-56'
                        : 'w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg'
                }
                side={inHeader ? undefined : isMobile ? 'bottom' : 'right'}
                align={inHeader ? 'end' : 'start'}
                sideOffset={inHeader ? undefined : 4}
            >
                <DropdownMenuLabel className="text-muted-foreground text-xs">
                    Organisations
                </DropdownMenuLabel>
                {organisations.map((organisation) => (
                    <DropdownMenuItem
                        key={organisation.id}
                        data-test="organisation-switcher-item"
                        className={
                            inHeader
                                ? 'cursor-pointer gap-2'
                                : 'cursor-pointer gap-2 p-2'
                        }
                        onSelect={() => switchOrganisation(organisation)}
                    >
                        {organisation.name}
                        {currentOrganisation?.id === organisation.id && (
                            <Check
                                className={
                                    inHeader
                                        ? 'ml-auto size-4'
                                        : 'ml-auto h-4 w-4'
                                }
                            />
                        )}
                    </DropdownMenuItem>
                ))}
                {canCreateOrganisation ? (
                    <>
                        <DropdownMenuSeparator />
                        <CreateOrganisationModal>
                            <DropdownMenuItem
                                data-test="organisation-switcher-new-organisation"
                                className={
                                    inHeader
                                        ? 'cursor-pointer gap-2'
                                        : 'cursor-pointer gap-2 p-2'
                                }
                                onSelect={(event) => event.preventDefault()}
                            >
                                <Plus
                                    className={inHeader ? 'size-4' : 'h-4 w-4'}
                                />
                                <span className="text-muted-foreground">
                                    New organisation
                                </span>
                            </DropdownMenuItem>
                        </CreateOrganisationModal>
                    </>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
