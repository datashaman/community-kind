import type { Auth } from '@/types/auth';
import type { Organisation } from '@/types/organisations';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            canCreateOrganisation: boolean;
            canViewParties: boolean;
            canViewPrograms: boolean;
            canViewIntakes: boolean;
            canViewDonations: boolean;
            canViewAudienceSegments: boolean;
            canViewSupporterJourneys: boolean;
            canViewVolunteers: boolean;
            canViewAudit: boolean;
            canViewOrganisationConfigurations: boolean;
            canViewImpactSnapshots: boolean;
            currentOrganisation: Organisation | null;
            organisations: Organisation[];
            demoSandbox: {
                pairId: string | null;
                expiresAt: string | null;
                persona: {
                    role: string;
                    organisation: string;
                    responsibility: string;
                    boundary: string;
                    tasks: {
                        label: string;
                        description: string;
                        href: string;
                    }[];
                } | null;
            } | null;
            [key: string]: unknown;
        };
    }
}
