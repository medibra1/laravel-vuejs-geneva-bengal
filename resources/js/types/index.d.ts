import type { AppNotifications, Color, Honeypot, MenuPage } from "./models";

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        roles: string[];
    };
    locale: string;
    // null on any page with no authenticated user — see
    // HandleInertiaRequests::notifications().
    notifications: AppNotifications | null;
    menuPages: MenuPage[];
    colors: Color[];
    socialLinks: {
        facebook: string | null;
        instagram: string | null;
        youtube: string | null;
        pinterest: string | null;
    };
    address: string | null;
    honeypot: Honeypot;
    alternateUrls: Record<string, string>;
};
