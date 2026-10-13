export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Organization = {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
    plan_name: string | null;
};

export type AuthAlumni = {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string;
    avatar?: string;
    must_change_password: boolean;
    unread_notifications: number;
    unread_messages: number;
    permissions: string[];
};

export type Auth = {
    user: User;
    alumni: AuthAlumni | null;
    organization: Organization | null;
    permissions: string[];
    isSuperAdmin: boolean;
};

export type SharedData = {
    name: string;
    auth: Auth;
    flash: {
        success: string | null;
        error: string | null;
    };
    sidebarOpen: boolean;
    [key: string]: unknown;
};
