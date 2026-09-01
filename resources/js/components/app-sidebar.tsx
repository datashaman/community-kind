import { Link, usePage } from '@inertiajs/react';
import {
    ClipboardList,
    CalendarDays,
    FolderGit2,
    HandCoins,
    LayoutGrid,
    ListFilter,
    MessagesSquare,
    Scale,
    ScrollText,
    Settings2,
    UsersRound,
    HeartHandshake,
    SlidersHorizontal,
    ChartNoAxesCombined,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { OrganisationSwitcher } from '@/components/organisation-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as audienceSegmentsIndex } from '@/routes/audience-segments';
import { index as auditIndex } from '@/routes/audit';
import { index as communityEngagementIndex } from '@/routes/community-engagement';
import { index as donationsIndex } from '@/routes/donations';
import { index as intakesIndex } from '@/routes/intakes';
import { index as configurationsIndex } from '@/routes/organisation-configurations';
import { index as impactSnapshotsIndex } from '@/routes/impact-snapshots';
import { index as partiesIndex } from '@/routes/parties';
import { index as programsIndex } from '@/routes/programs';
import { index as supporterJourneysIndex } from '@/routes/supporter-journeys';
import { index as volunteersIndex } from '@/routes/volunteers';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const dashboardUrl = page.props.currentOrganisation
        ? dashboard(page.props.currentOrganisation.slug)
        : '/';

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        ...(page.props.canViewParties && page.props.currentOrganisation
            ? [
                  {
                      title: 'Party profiles',
                      href: partiesIndex(page.props.currentOrganisation.slug),
                      icon: UsersRound,
                  },
              ]
            : []),
        ...(page.props.canViewIntakes && page.props.currentOrganisation
            ? [
                  {
                      title: 'Service requests',
                      href: intakesIndex(page.props.currentOrganisation.slug),
                      icon: ClipboardList,
                  },
              ]
            : []),
        ...(page.props.canViewDonations && page.props.currentOrganisation
            ? [
                  {
                      title: 'Simulated donations',
                      href: donationsIndex(page.props.currentOrganisation.slug),
                      icon: HandCoins,
                  },
              ]
            : []),
        ...(page.props.canViewAudienceSegments && page.props.currentOrganisation
            ? [
                  {
                      title: 'Saved audiences',
                      href: audienceSegmentsIndex(
                          page.props.currentOrganisation.slug,
                      ),
                      icon: ListFilter,
                  },
              ]
            : []),
        ...(page.props.canViewSupporterJourneys &&
        page.props.currentOrganisation
            ? [
                  {
                      title: 'Welcome journeys',
                      href: supporterJourneysIndex(
                          page.props.currentOrganisation.slug,
                      ),
                      icon: MessagesSquare,
                  },
              ]
            : []),
        ...(page.props.canViewVolunteers && page.props.currentOrganisation
            ? [
                  {
                      title: 'Volunteering',
                      href: volunteersIndex(
                          page.props.currentOrganisation.slug,
                      ),
                      icon: HeartHandshake,
                  },
              ]
            : []),
        ...(page.props.canViewVolunteers && page.props.currentOrganisation
            ? [
                  {
                      title: 'Community engagement',
                      href: communityEngagementIndex(
                          page.props.currentOrganisation.slug,
                      ),
                      icon: CalendarDays,
                  },
              ]
            : []),
        ...(page.props.canViewPrograms && page.props.currentOrganisation
            ? [
                  {
                      title: 'Program configuration',
                      href: programsIndex(page.props.currentOrganisation.slug),
                      icon: Settings2,
                  },
              ]
            : []),
        ...(page.props.canViewOrganisationConfigurations &&
        page.props.currentOrganisation
            ? [
                  {
                      title: 'Organisation configuration',
                      href: configurationsIndex(
                          page.props.currentOrganisation.slug,
                      ),
                      icon: SlidersHorizontal,
                  },
              ]
            : []),
        ...(page.props.canViewImpactSnapshots && page.props.currentOrganisation
            ? [
                  {
                      title: 'Impact packs',
                      href: impactSnapshotsIndex(
                          page.props.currentOrganisation.slug,
                      ),
                      icon: ChartNoAxesCombined,
                  },
              ]
            : []),
        ...(page.props.canViewAudit && page.props.currentOrganisation
            ? [
                  {
                      title: 'Audit history',
                      href: auditIndex(page.props.currentOrganisation.slug),
                      icon: ScrollText,
                  },
              ]
            : []),
    ];

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/datashaman/community-kind',
            icon: FolderGit2,
        },
        {
            title: 'Source and licence',
            href: '/source-and-licence',
            icon: Scale,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <OrganisationSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
