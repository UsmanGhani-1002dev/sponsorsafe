export interface AuthUser {
    name: string;
    email: string;
    role: 'admin' | 'employee';
    initials: string;
    business: string | null;
}

/** "Get started" set-up steps (App\Support\SetupChecklist). */
export interface Setup {
    steps: { key: string; title: string; detail: string; done: boolean; href: string; action: string }[];
    done: number;
    total: number;
}

export interface SharedProps {
    appName: string;
    auth: { user: AuthUser | null };
    flash: { success?: string | null; error?: string | null; contactSent?: { first: string; email: string } | null };
    ops: { newEnquiries: number } | null;
    billing: { graceEnds: string } | null;
    setupNudge?: Setup | null;
    errors: Record<string, string>;
    [key: string]: unknown;
}
