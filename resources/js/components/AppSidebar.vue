<script setup lang="ts">
/* =========================================================
   IMPORTS
========================================================= */

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { can } from '@/utils/permissions'
import * as reports from '@/routes/reports'

import {
    LayoutGrid,
    Boxes,
    QrCode,
    Users,
    Shield,
    Key,
    Building2,
    Layers,
    Truck,
    Wrench,
    Tag,
    Database,
    Archive,
    Trash2,
    Calculator,
    Clock,
    Activity,
    FileText,
    Bell,
    CheckCircle,
    ClipboardList,
    ArrowRightLeft,
    BarChart3,
    PieChart,
    Gauge,
    ShieldCheck,
    AlertTriangle,
    ClipboardCheck,
    CreditCard,
} from 'lucide-vue-next'

/* COMPONENTS */
import NavMain from '@/components/NavMain.vue'
import NavUser from '@/components/NavUser.vue'

import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar'

import AppLogo from './AppLogo.vue'

/* ROUTES */
import { dashboard } from '@/routes'
import users from '@/routes/users'
import roles from '@/routes/roles'
import permissions from '@/routes/permissions'
import organizations from '@/routes/organizations'
import units from '@/routes/units'
import suppliers from '@/routes/suppliers'
import maintenanceproviders from '@/routes/maintenanceproviders'
import assetnames from '@/routes/assetnames'
import assets from '@/routes/assets'
import assetQrcodes from '@/routes/assetqrcodes'
import applinkqrs from '@/routes/links'
import sources from '@/routes/sources'
import depreciationreports from '@/routes/depreciationreports'
import maintenanceRequests from '@/routes/maintenance-requests'
import assetRequests from '@/routes/asset-requests'
import assetTransfers from '@/routes/asset-transfers'


import type { NavItem } from '@/types'

/* =========================================================
   SUPER ADMIN AUTOMATIC MENU ACCESS
   Super Admin does not need individual *.menu permissions.
========================================================= */

type RoleValue = string | { name?: string | null }

const page = usePage<any>()
const isSpecialRole = computed(() => Boolean(page.props.specialRole?.active))
const homeUrl = computed(() => page.props.specialRole?.active ? (page.props.specialRole.landingUrl ?? '#') : dashboard().url)

const isSuperAdmin = computed(() => {
    const roles: RoleValue[] =
        page.props.auth?.user?.roles ??
        page.props.auth?.roles ??
        []

    return roles.some((role) => {
        const roleName =
            typeof role === 'string'
                ? role
                : role?.name ?? ''

        return roleName.trim().toLowerCase() === 'super admin'
    })
})

const hasPermission = (permission: string): boolean => can(permission)

const hasMenuAccess = (permission: string): boolean =>
    isSuperAdmin.value || hasPermission(permission) || (page.props.specialRole?.menuPermissions ?? []).includes(permission)

/* =========================================================
   ROLES & PERMISSION CHILDREN
   Every child has its own *.menu permission.
   Super Admin receives automatic access through hasMenuAccess().
========================================================= */

const rolesPermissionChildren: NavItem[] = []

if (hasMenuAccess('roles.menu')) {
    rolesPermissionChildren.push({
        title: 'Roles',
        href: roles.index(),
        icon: Shield,
    })
}

if (hasMenuAccess('permissions.menu')) {
    rolesPermissionChildren.push({
        title: 'Permissions',
        href: permissions.index(),
        icon: Key,
    })
}


const canAccessPermissionGuide = isSuperAdmin.value || [
    'roles.view', 'view.roles', 'roles.create', 'create.roles', 'roles.edit', 'edit.roles',
].some(hasPermission)

if (canAccessPermissionGuide) {
    rolesPermissionChildren.push({
        title: 'Permissions and Landing Pages Guide',
        href: '/role-permission-guide',
        icon: Shield,
    })
}

const securityAuditChildren: NavItem[] = []

if (hasMenuAccess('login-tracker.menu')) {
    securityAuditChildren.push({
        title: 'Login Tracker',
        href: '/login-trackers',
        icon: Shield,
    })
}

if (hasMenuAccess('activity-tracker.menu')) {
    securityAuditChildren.push({
        title: 'Activity Tracker',
        href: '/activity-trackers',
        icon: Activity,
    })
}

if (hasMenuAccess('audit-logs.menu')) {
    securityAuditChildren.push({
        title: 'Audit Logs',
        href: '/audit-logs',
        icon: FileText,
    })
}

if (hasMenuAccess('notifications.menu')) {
    securityAuditChildren.push({
        title: 'Notifications',
        href: '/notifications',
        icon: Bell,
    })
}

if (hasMenuAccess('asset-lifecycle.menu')) {
    securityAuditChildren.push({
        title: 'Asset Lifecycle',
        href: '/asset-lifecycle-histories',
        icon: Clock,
    })
}

/* =========================================================
   CATEGORIES CHILDREN
========================================================= */

const categoryChildren: NavItem[] = []

if (hasMenuAccess('organizations.menu')) {
    categoryChildren.push({
        title: 'Organizations',
        href: organizations.index(),
        icon: Building2,
    })
}

if (hasMenuAccess('units.menu')) {
    categoryChildren.push({
        title: 'Units',
        href: units.index(),
        icon: Layers,
    })
}

if (hasMenuAccess('suppliers.menu')) {
    categoryChildren.push({
        title: 'Suppliers',
        href: suppliers.index(),
        icon: Truck,
    })
}

if (hasMenuAccess('maintenance-providers.menu')) {
    categoryChildren.push({
        title: 'Maintenance Providers',
        href: maintenanceproviders.index(),
        icon: Wrench,
    })
}

if (hasMenuAccess('asset-names.menu')) {
    categoryChildren.push({
        title: 'Asset Names',
        href: assetnames.index(),
        icon: Tag,
    })
}

if (hasMenuAccess('asset-sources.menu')) {
    categoryChildren.push({
        title: 'Asset Sources',
        href: sources.index(),
        icon: Database,
    })
}

/* =========================================================
   ADVANCED ANALYTICS CHILDREN
========================================================= */

const advancedAnalyticsChildren: NavItem[] = []

if (!isSpecialRole.value && hasMenuAccess('analytics-dashboard.menu')) {
    advancedAnalyticsChildren.push({
        title: 'Analytics Dashboard',
        href: '/advanced-analytics',
        icon: BarChart3,
    })
}

if (hasMenuAccess('asset-distribution-count.menu')) {
    advancedAnalyticsChildren.push({
        title: 'Asset Distribution Count',
        href: '/advanced-analytics/distribution-count',
        icon: PieChart,
    })
}

if (hasMenuAccess('data-quality-index.menu')) {
    advancedAnalyticsChildren.push({
        title: 'Data Quality Index',
        href: '/advanced-analytics/data-quality',
        icon: Gauge,
    })
}

if (hasMenuAccess('maintenance-analytics.menu')) {
    advancedAnalyticsChildren.push({
        title: 'Maintenance Analytics',
        href: '/advanced-analytics/maintenance',
        icon: Wrench,
    })
}

if (hasMenuAccess('lifecycle-risk-analytics.menu')) {
    advancedAnalyticsChildren.push({
        title: 'Lifecycle Risk Analytics',
        href: '/advanced-analytics/lifecycle-risk',
        icon: AlertTriangle,
    })
}

if (hasMenuAccess('warranty-end-of-life.menu')) {
    advancedAnalyticsChildren.push({
        title: 'Warranty & End-of-Life',
        href: '/advanced-analytics/warranty-end-of-life',
        icon: ShieldCheck,
    })
}

if (hasMenuAccess('implementation-evidence.menu')) {
    advancedAnalyticsChildren.push({
        title: 'Implementation Evidence',
        href: '/advanced-analytics/implementation-evidence',
        icon: ClipboardCheck,
    })
}

/* =========================================================
   MANAGEMENT REPORTS CHILDREN
========================================================= */

const managementReportChildren: NavItem[] = []

if (hasMenuAccess('management-reports.asset-purchases.menu')) {
    managementReportChildren.push({
        title: 'New Assets Purchased',
        href: '/management-reports/asset-purchases',
        icon: Boxes,
    })
}

if (hasMenuAccess('management-reports.maintenance.menu')) {
    managementReportChildren.push({
        title: 'Periodic Maintenance Report',
        href: '/management-reports/maintenance',
        icon: Wrench,
    })
}

if (hasMenuAccess('management-reports.annual-assets.menu')) {
    managementReportChildren.push({
        title: 'Annual Full Asset Report',
        href: '/management-reports/annual-assets',
        icon: BarChart3,
    })
}

/* =========================================================
   MAIN NAVIGATION
   REARRANGED ONLY:
   - Existing menu names are preserved.
   - Existing routes are preserved.
   - Existing permission names are preserved.
   - Existing dropdown children are preserved.
   - Super Admin automatic bypass remains unchanged.
========================================================= */

const mainNavItems: NavItem[] = []
if (isSpecialRole.value && page.props.specialRole?.landingUrl) {
    mainNavItems.push({ title: 'My Workspace', href: page.props.specialRole.landingUrl, icon: LayoutGrid })
}

/* 1. DASHBOARD */
if (!isSpecialRole.value && hasMenuAccess('dashboard.menu')) {
    mainNavItems.push({
        title: 'Dashboard',
        href: dashboard().url,
        icon: LayoutGrid,
    })
}

/* 2. CORE ASSET MANAGEMENT */
if (hasMenuAccess('assets.menu')) {
    mainNavItems.push({
        title: 'Asset Management',
        href: assets.index(),
        icon: Boxes,
    })
}

if (hasMenuAccess('asset-qrcodes.menu')) {
    mainNavItems.push({
        title: 'Asset QR Codes',
        href: assetQrcodes.index(),
        icon: QrCode,
    })
}

/* 9. STANDARD REPORTS */
if (hasMenuAccess('assets-inventory-by-office.menu')) {
    mainNavItems.push({
        title: 'Assets Inventory by Office',
        href: reports.inventoryOffice(),
        icon: Building2,
    })
}

/* 3. ASSET REQUEST WORKFLOW */
if (hasMenuAccess('asset-requests.menu')) {
    mainNavItems.push({
        title: 'Asset Requests',
        href: assetRequests.myRequests(),
        icon: ClipboardList,
    })
}

if (hasMenuAccess('asset-request-approval.menu')) {
    mainNavItems.push({
        title: 'Asset Request Approval',
        href: assetRequests.index(),
        icon: ClipboardList,
    })
}

/* 4. ASSET TRANSFER WORKFLOW */
if (hasMenuAccess('asset-transfers.menu')) {
    mainNavItems.push({
        title: 'Asset Transfers',
        href: assetTransfers.myTransfers(),
        icon: ArrowRightLeft,
    })
}

if (hasMenuAccess('asset-transfer-approval.menu')) {
    mainNavItems.push({
        title: 'Asset Transfer Approval',
        href: assetTransfers.index(),
        icon: ArrowRightLeft,
    })
}

/* 5. MAINTENANCE WORKFLOW */
if (hasMenuAccess('maintenance-requests.menu')) {
    mainNavItems.push({
        title: 'Maintenance Requests',
        href: maintenanceRequests.usermaintenancerequest(),
        icon: Wrench,
    })
}

if (hasMenuAccess('maintenance-request-approval.menu')) {
    mainNavItems.push({
        title: 'Maintenance Certification',
        href: '/maintenance-requests',
        icon: Wrench,
    })
}

/* 6. GENERAL APPROVAL */
if (hasMenuAccess('approval-workflow.menu')) {
    mainNavItems.push({
        title: 'Approval Workflow',
        href: '/approval-requests',
        icon: CheckCircle,
    })
}

/* 7. ACCOUNTS / PAYMENT */
if (hasMenuAccess('accounts-payment.menu')) {
    mainNavItems.push({
        title: 'Accounts Payment',
        href: '/accounts/maintenance-payments',
        icon: CreditCard,
    })
}

/* 8. CURRENT ASSET STATUS */
if (hasMenuAccess('active-assets.menu')) {
    mainNavItems.push({
        title: 'Active Assets',
        href: reports.active(),
        icon: CheckCircle,
    })
}

if (hasMenuAccess('under-maintenance-assets.menu')) {
    mainNavItems.push({
        title: 'Under Maintenance Assets',
        href: reports.underMaintenance(),
        icon: Wrench,
    })
}

if (hasMenuAccess('obsolete-assets.menu')) {
    mainNavItems.push({
        title: 'Obsolete Assets',
        href: reports.obsolete(),
        icon: Archive,
    })
}

if (hasMenuAccess('disposed-assets.menu')) {
    mainNavItems.push({
        title: 'Disposed Assets',
        href: reports.disposed(),
        icon: Trash2,
    })
}



if (hasMenuAccess('yearly-purchased-assets.menu')) {
    mainNavItems.push({
        title: 'Yearly Purchased Assets',
        href: reports.yearlyPurchased(),
        icon: Clock,
    })
}

if (hasMenuAccess('assets-count-by-year.menu')) {
    mainNavItems.push({
        title: 'Assets count by Year',
        href: reports.assetcountByYear(),
        icon: Layers,
    })
}

if (hasMenuAccess('assets-depreciation-report.menu')) {
    mainNavItems.push({
        title: 'Assets Depreciation Report',
        href: depreciationreports.index(),
        icon: Calculator,
    })
}

if (hasMenuAccess('maintenance-cost-summary.menu')) {
    mainNavItems.push({
        title: 'Maintenance Cost Summary',
        href: reports.maintenanceCost(),
        icon: Wrench,
    })
}

/* 10. MANAGEMENT / ANALYTICS */
if (
    managementReportChildren.length > 0
) {
    mainNavItems.push({
        title: 'Management Reports',
        icon: FileText,
        children: managementReportChildren,
    })
}

if (
    advancedAnalyticsChildren.length > 0
) {
    mainNavItems.push({
        title: 'Advanced Analytics',
        icon: BarChart3,
        children: advancedAnalyticsChildren,
    })
}

if (hasMenuAccess('advanced-reports.menu')) {
    mainNavItems.push({
        title: 'Advanced Reports',
        href: '/advanced-reports',
        icon: FileText,
    })
}

if (hasMenuAccess('scheduled-reports.manage')) {
    mainNavItems.push({ title: 'Scheduled Reports', href: '/scheduled-reports', icon: FileText })
}

/* 11. ADMINISTRATION / SECURITY */
if (hasMenuAccess('users.menu')) {
    mainNavItems.push({
        title: 'Users Management',
        href: users.index(),
        icon: Users,
    })
}

if (hasMenuAccess('monitor-users-online.menu')) {
    mainNavItems.push({
        title: 'User Session Monitor',
        href: '/user-session-monitor',
        icon: Activity,
    })
}

if (
    (hasMenuAccess('roles-permissions.menu') || canAccessPermissionGuide) &&
    rolesPermissionChildren.length > 0
) {
    mainNavItems.push({
        title: 'Roles and Permission',
        icon: Shield,
        children: rolesPermissionChildren,
    })
}

if (
    securityAuditChildren.length > 0
) {
    mainNavItems.push({
        title: 'Security & Audit',
        icon: Activity,
        children: securityAuditChildren,
    })
}

if (
    categoryChildren.length > 0
) {
    mainNavItems.push({
        title: 'Categories',
        icon: Layers,
        children: categoryChildren,
    })
}

/* =========================================================
   ACTIVE SIDEBAR SCROLL CONTINUITY
   Preserve sidebar scroll separately from page scroll. Reveal the selected
   route only if it is outside the current sidebar viewport.
========================================================= */
const sidebarContentRef = ref<InstanceType<typeof SidebarContent> | null>(null)
const { isMobile, openMobile, state } = useSidebar()

const sidebarScrollKey = (): string => {
    const id = page.props.auth?.user?.id ?? 'guest'
    return `ghanadams-sidebar-scroll-v1:${id}:${isMobile.value ? 'mobile' : 'desktop'}`
}

const sidebarScrollContainer = (): HTMLElement | null => {
    const element = sidebarContentRef.value?.$el
    return element instanceof HTMLElement ? element : null
}

const rememberSidebarScroll = (event?: Event): void => {
    const container = (event?.currentTarget as HTMLElement | null) ?? sidebarScrollContainer()
    if (!container) return
    try {
        sessionStorage.setItem(sidebarScrollKey(), String(container.scrollTop))
    } catch {
        // Storage may be unavailable; navigation must still work.
    }
}

const restoreSidebarScroll = (container: HTMLElement): void => {
    try {
        const stored = sessionStorage.getItem(sidebarScrollKey())
        if (stored === null) return
        const position = Number(stored)
        if (Number.isFinite(position) && position >= 0) container.scrollTop = position
    } catch {
        // Continue by revealing the active link below.
    }
}

const selectedSidebarLink = (container: HTMLElement): HTMLElement | null => {
    // NavMain sets aria-current on the actual active route (not its group).
    return container.querySelector<HTMLElement>('a[aria-current="page"]')
}

const keepCurrentPageVisible = (): void => {
    if (isMobile.value && !openMobile.value) return
    if (!isMobile.value && state.value === 'collapsed') return
    const container = sidebarScrollContainer()
    if (!container) return
    const selected = selectedSidebarLink(container)
    if (!selected || selected.getClientRects().length === 0) return

    const area = container.getBoundingClientRect()
    const item = selected.getBoundingClientRect()
    const margin = 12
    // Change ONLY the menu scroll offset, never document/window scrolling.
    if (item.bottom > area.bottom - margin) {
        container.scrollTop += item.bottom - area.bottom + margin
    } else if (item.top < area.top + margin) {
        container.scrollTop -= area.top + margin - item.top
    }
    rememberSidebarScroll()
}

let revealGeneration = 0
const pendingRevealTimers: number[] = []
const cancelPendingReveal = (): void => {
    revealGeneration += 1
    for (const timeout of pendingRevealTimers) window.clearTimeout(timeout)
    pendingRevealTimers.length = 0
}

const showSelectedPage = async (restore = false): Promise<void> => {
    cancelPendingReveal()
    const generation = revealGeneration
    await nextTick()
    const reveal = (): void => {
        if (generation !== revealGeneration) return
        const container = sidebarScrollContainer()
        if (!container) return
        if (isMobile.value && !openMobile.value) return
        if (restore) restoreSidebarScroll(container)
        keepCurrentPageVisible()
    }
    window.requestAnimationFrame(reveal)
    // Inertia/Sheet transitions and submenu expansion can finish after nextTick.
    for (const delay of [100, 250, 450]) {
        pendingRevealTimers.push(window.setTimeout(reveal, delay))
    }
}

onMounted(() => { void showSelectedPage(true) })
onBeforeUnmount(() => {
    rememberSidebarScroll()
    cancelPendingReveal()
})
watch(() => page.url, () => { void showSelectedPage() }, { flush: 'post' })
watch(isMobile, () => { void showSelectedPage(true) })
watch(openMobile, (opened) => { if (opened) void showSelectedPage(true) })



// Special roles see their existing permitted pages as direct sidebar links.
const displayedSidebarItems = computed<NavItem[]>(() => {
    if (!isSpecialRole.value) return mainNavItems
    // The server uses the same route permissions for navigation and landing choices.
    const allowed = page.props.specialRole?.pages ?? []
    return allowed.map((item: { label: string; url: string }) => ({
        title: item.label,
        href: item.url,
        icon: LayoutGrid,
    }))
})


const dashboardFirstSidebarItems = computed(() =>
    [...displayedSidebarItems.value].sort(
        (a, b) =>
            Number(b.title.trim().toLowerCase() === 'dashboard') -
            Number(a.title.trim().toLowerCase() === 'dashboard')
    )
)
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <!-- HEADER -->
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <!-- MAIN CONTENT -->
        <SidebarContent
            ref="sidebarContentRef"
            @scroll.passive="rememberSidebarScroll"
            @wheel.passive="cancelPendingReveal"
            @touchstart.passive="cancelPendingReveal"
            @pointerdown="cancelPendingReveal"
        >
            <NavMain :items="dashboardFirstSidebarItems" />
        </SidebarContent>

        <!-- FOOTER: POSITION UNCHANGED -->
        <SidebarFooter>
            <SidebarMenu v-if="!isSpecialRole && hasMenuAccess('recycle-bin.menu')">
                <SidebarMenuItem>
                    <SidebarMenuButton as-child :is-active="page.url.split('?')[0] === '/trash'">
                        <Link href="/trash" :aria-current="page.url.split('?')[0] === '/trash' ? 'page' : undefined">
                            <Trash2 class="size-4" />
                            <span>Recycle Bin</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <NavUser />
        </SidebarFooter>
    </Sidebar>

    <slot />
</template>
