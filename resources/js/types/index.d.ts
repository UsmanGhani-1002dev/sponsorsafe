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
    flash: { success?: string | null; error?: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}
