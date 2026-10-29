import type { InertiaLinkProps } from '@inertiajs/vue3'
import type { LucideIcon } from 'lucide-vue-next'

/* =========================
   DOMAIN TYPES
========================= */
export interface Role {
    id: number
    name: string
}

export interface Unit {
    id: number
    name: string
}

/* =========================
   AUTH & USER
========================= */
export interface User {
    id: number
    name: string
    email: string
    email_verified_at: string | null

    user_status: 'Active' | 'Inactive' | 'IsSystem'
    sex?: string | null
    contact_number?: string | null
    date_of_birth?: string | null
    profile_image?: string | null

    roles?: Role[]
    unit?: Unit | null   // ✅ IMPORTANT

    created_at: string
    updated_at: string

    [key: string]: unknown
}

export interface Auth {
    user: User | null
}

/* =========================
   NAVIGATION
========================= */
export interface NavItem {
    title: string
    href?: NonNullable<InertiaLinkProps['href']>

    icon?: LucideIcon
    isActive?: boolean
    children?: NavItem[]
}

/* =========================
   BREADCRUMBS
========================= */
export interface BreadcrumbItem {
    title: string
    href: string
}

/* =========================
   PAGE PROPS (GLOBAL)
========================= */
export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string
    auth: Auth
    sidebarOpen: boolean
}
