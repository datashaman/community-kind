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
            canViewAudit: boolean;
            currentOrganisation: Organisation | null;
            organisations: Organisation[];
            [key: string]: unknown;
        };
    }
}
