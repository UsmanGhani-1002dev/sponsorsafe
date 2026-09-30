export interface AuthUser {
    name: string;
    email: string;
    role: 'admin' | 'employee';
    initials: string;
    business: string | null;
}

export interface SharedProps {
    appName: string;
    auth: { user: AuthUser | null };
    flash: { success?: string | null; error?: string | null; contactSent?: { first: string; email: string } | null };
    ops: { newEnquiries: number } | null;
    errors: Record<string, string>;
    [key: string]: unknown;
}
