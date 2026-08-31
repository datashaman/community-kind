import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

export function resolvePageLayout(name: string) {
    switch (true) {
        case name === 'welcome':
        case name.startsWith('demo/'):
            return null;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
        case name.startsWith('organisations/'):
            return [AppLayout, SettingsLayout];
        default:
            return AppLayout;
    }
}
