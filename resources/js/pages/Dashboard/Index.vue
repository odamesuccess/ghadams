<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { index as assetResearchIndex } from '@/routes/asset-research'
import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import { type BreadcrumbItem } from '@/types'

import {
    Chart as ChartJS,
    BarElement,
    ArcElement,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Title,
    Filler,
    type ChartData,
    type ChartOptions,
} from 'chart.js'

import { Bar, Doughnut, Line } from 'vue-chartjs'

import {
    Activity,
    AlertTriangle,
    Archive,
    BadgeCheck,
    Banknote,
    BarChart3,
    Boxes,
    Building2,
    CalendarClock,
    CheckCircle2,
    CircleDollarSign,
    MapPin,
    PackageCheck,
    ShieldAlert,
    Sparkles,
    TrendingUp,
    Wrench,
} from 'lucide-vue-next'

ChartJS.register(
    BarElement,
    ArcElement,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Title,
    Filler,
)

/* ================= TYPES ================= */

interface YearRow {
    year: number
    total: number
    value: number
}

interface LocationRow {
    location: string
    total: number
}

interface AssetNameRow {
    name: string
    short: string | null
    total: number
}

interface AssetYearSummaryRow {
    year: number
    quantity: number
    cost: number
}

interface DashboardProps {
    totalAssets: number
    activeAssets: number
    disposedAssets: number
    underMaintenanceAssets: number
    obsoleteAssets: number
    totalValue: number
    depreciatedAssetValue: number
    depreciableAssetCount: number
    assetsByStatus: Record<string, number>
    assetsByYear: YearRow[]
    assetsByLocation: LocationRow[]
    assetsByName: AssetNameRow[]
    assetYearSummary: AssetYearSummaryRow[]
    expiringSoon: number
    securityStats?: Record<string, number>
    aiResearchPending?: number | null
}

const props = defineProps<DashboardProps>()

/* ================= BREADCRUMBS ================= */

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
]

/* ================= FORMATTERS ================= */

const formatCurrency = (value?: number) =>
    new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
        minimumFractionDigits: 2,
    }).format(Number(value ?? 0))

const formatShortCurrency = (value?: number) =>
    new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(Number(value ?? 0))

const formatNumber = (value?: number | string | unknown) =>
    new Intl.NumberFormat('en-GH').format(Number(value ?? 0))

const formatPercent = (value?: number) =>
    `${Number(value ?? 0).toFixed(1)}%`

const toTitle = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase())

const safePercent = (value: number, total: number) =>
    total > 0 ? (value / total) * 100 : 0

const abbreviate = (name?: string, maxLength = 14) => {
    const value = String(name ?? 'Unknown')
        .replace(/\s+/g, ' ')
        .trim()

    if (!value) {
        return 'Unknown'
    }

    const replacements: Array<[RegExp, string]> = [
        [/\bHeadquarters\b/gi, 'HQ'],
        [/\bHeadquarter\b/gi, 'HQ'],
        [/\bRegional Directorate\b/gi, 'Reg. Dir.'],
        [/\bDistrict Directorate\b/gi, 'Dist. Dir.'],
        [/\bDirectorate\b/gi, 'Dir.'],
        [/\bAdministration\b/gi, 'Admin.'],
        [/\bAdministrative\b/gi, 'Admin.'],
        [/\bDevelopment\b/gi, 'Dev.'],
        [/\bEducation\b/gi, 'Edu.'],
        [/\bSpecial Education\b/gi, 'Special Edu.'],
        [/\bQuality Assurance\b/gi, 'QA'],
        [/\bAssessment\b/gi, 'Assess.'],
        [/\bDepartment\b/gi, 'Dept.'],
        [/\bServices\b/gi, 'Svc.'],
        [/\bService\b/gi, 'Svc.'],
        [/\bOffice\b/gi, 'Off.'],
        [/\bBuilding\b/gi, 'Bldg.'],
        [/\bBlock\b/gi, 'Blk.'],
        [/\bInformation Technology\b/gi, 'IT'],
        [/\bInformation Communication Technology\b/gi, 'ICT'],
        [/\bHuman Resource\b/gi, 'HR'],
        [/\bHuman Resources\b/gi, 'HR'],
        [/\bFinance\b/gi, 'Fin.'],
        [/\bAccounts\b/gi, 'Acct.'],
        [/\bAccounting\b/gi, 'Acct.'],
        [/\bProcurement\b/gi, 'Proc.'],
        [/\bPlanning\b/gi, 'Plan.'],
        [/\bStatistics\b/gi, 'Stats.'],
        [/\bEvaluation\b/gi, 'Eval.'],
        [/\bMonitoring\b/gi, 'Mon.'],
        [/\bTraining\b/gi, 'Train.'],
        [/\bResearch\b/gi, 'Res.'],
        [/\bLibrary\b/gi, 'Lib.'],
        [/\bLaboratory\b/gi, 'Lab.'],
        [/\bComputer\b/gi, 'Comp.'],
        [/\bScience\b/gi, 'Sci.'],
        [/\bTechnology\b/gi, 'Tech.'],
        [/\bMaintenance\b/gi, 'Maint.'],
        [/\bSecurity\b/gi, 'Sec.'],
        [/\bTransport\b/gi, 'Trans.'],
        [/\bStores\b/gi, 'Stores'],
        [/\bStore\b/gi, 'Store'],
    ]

    let shortName = value

    for (const [pattern, replacement] of replacements) {
        shortName = shortName.replace(pattern, replacement)
    }

    shortName = shortName
        .replace(/\s+/g, ' ')
        .replace(/\s+\./g, '.')
        .trim()

    if (shortName.length > maxLength) {
        const words = shortName.split(' ')

        const importantWords = words.filter((word) => {
            const clean = word.toLowerCase().replace(/[^\w]/g, '')

            return ![
                'the',
                'and',
                'of',
                'for',
                'in',
                'at',
                'main',
                'unit',
                'section',
            ].includes(clean)
        })

        shortName = importantWords.slice(0, 2).join(' ')

        if (shortName.length > maxLength) {
            shortName = `${shortName.slice(0, maxLength - 1)}…`
        }
    }

    return shortName || value
}

/* ================= DASHBOARD DERIVED DATA ================= */

const totalAssets = computed(() => Number(props.totalAssets ?? 0))
const activeAssets = computed(() => Number(props.activeAssets ?? 0))
const disposedAssets = computed(() => Number(props.disposedAssets ?? 0))
const obsoleteAssets = computed(() => Number(props.obsoleteAssets ?? 0))
const underMaintenanceAssets = computed(() => Number(props.underMaintenanceAssets ?? 0))
const expiringSoon = computed(() => Number(props.expiringSoon ?? 0))
const totalValue = computed(() => Number(props.totalValue ?? 0))
const depreciatedAssetValue = computed(() => Number(props.depreciatedAssetValue ?? 0))
const depreciableAssetCount = computed(() =>
    Number(props.depreciableAssetCount ?? 0),
)

const activeRate = computed(() =>
    safePercent(activeAssets.value, totalAssets.value),
)

const inactiveRate = computed(() =>
    safePercent(
        disposedAssets.value + obsoleteAssets.value + underMaintenanceAssets.value,
        totalAssets.value,
    ),
)

const averageAssetValue = computed(() =>
    totalAssets.value > 0 ? totalValue.value / totalAssets.value : 0,
)

const lifecycleRiskCount = computed(() =>
    disposedAssets.value + obsoleteAssets.value + underMaintenanceAssets.value,
)

const sortedYears = computed(() =>
    [...(props.assetsByYear ?? [])]
        .map((row) => ({
            year: Number(row.year),
            total: Number(row.total ?? 0),
            value: Number(row.value ?? 0),
        }))
        .sort((a, b) => a.year - b.year),
)

const latestYear = computed(() =>
    [...sortedYears.value].sort((a, b) => b.year - a.year)[0],
)

const previousYear = computed(() =>
    [...sortedYears.value].sort((a, b) => b.year - a.year)[1],
)

const latestYearGrowth = computed(() => {
    if (!latestYear.value || !previousYear.value) {
        return 0
    }

    if (previousYear.value.total <= 0) {
        return latestYear.value.total > 0 ? 100 : 0
    }

    return ((latestYear.value.total - previousYear.value.total) / previousYear.value.total) * 100
})

const statusRows = computed(() =>
    Object.entries(props.assetsByStatus ?? {})
        .map(([status, total]) => ({
            status,
            label: toTitle(status),
            total: Number(total ?? 0),
            percentage: safePercent(Number(total ?? 0), totalAssets.value),
        }))
        .sort((a, b) => b.total - a.total),
)

const topAssetName = computed(() => props.assetsByName?.[0])
const topLocation = computed(() => props.assetsByLocation?.[0])

const fixedYearCards = computed(() => {
    const rows = [...(props.assetYearSummary ?? [])]
        .map((row) => ({
            year: Number(row.year),
            quantity: Number(row.quantity ?? 0),
            cost: Number(row.cost ?? 0),
        }))
        .filter((row) => Number.isFinite(row.year))

    const recentYearCards = rows
        .filter((row) => [2026, 2025, 2024, 2023].includes(row.year))
        .sort((a, b) => b.year - a.year)
        .map((row) => ({
            year: row.year,
            title: `${row.year} Purchase Year`,
            quantity: row.quantity,
            cost: row.cost,
            isRange: false,
        }))

    const yearsUpTo2022 = rows.filter((row) => row.year <= 2022)

    const firstPurchaseYear =
        yearsUpTo2022.length > 0
            ? Math.min(...yearsUpTo2022.map((row) => row.year))
            : 2022

    const upTo2022Card = {
        year: 2022,
        title:
            yearsUpTo2022.length > 0
                ? `${firstPurchaseYear} - 2022 Purchase Years`
                : 'All Previous Years up to 2022 Purchase Years',
        quantity: yearsUpTo2022.reduce((sum, row) => sum + row.quantity, 0),
        cost: yearsUpTo2022.reduce((sum, row) => sum + row.cost, 0),
        isRange: true,
    }

    return [...recentYearCards, upTo2022Card]
})

/* ================= KPI CARDS ================= */

const kpiCards = computed(() => [
    {
        title: 'Total Assets',
        value: formatNumber(totalAssets.value),
        subtitle: 'Registered institutional assets',
        icon: Boxes,
        badge: 'Inventory',
        cardClass: 'from-blue-950/90 via-slate-950 to-slate-950',
        iconClass: 'bg-blue-500/15 text-blue-300 ring-blue-400/20',
        valueClass: 'text-white',
    },
    {
        title: 'Active Assets',
        value: formatNumber(activeAssets.value),
        subtitle: `${formatPercent(activeRate.value)} of total inventory`,
        icon: CheckCircle2,
        badge: 'Healthy',
        cardClass: 'from-emerald-950/90 via-slate-950 to-slate-950',
        iconClass: 'bg-emerald-500/15 text-emerald-300 ring-emerald-400/20',
        valueClass: 'text-emerald-200',
    },
    {
        title: 'Maintenance',
        value: formatNumber(underMaintenanceAssets.value),
        subtitle: 'Assets currently under servicing',
        icon: Wrench,
        badge: 'Action',
        cardClass: 'from-amber-950/90 via-slate-950 to-slate-950',
        iconClass: 'bg-amber-500/15 text-amber-300 ring-amber-400/20',
        valueClass: 'text-amber-200',
    },
    {
        title: 'Expiring Warranty',
        value: formatNumber(expiringSoon.value),
        subtitle: 'Active assets ending within 90 days',
        icon: CalendarClock,
        badge: 'Alert',
        cardClass: 'from-rose-950/90 via-slate-950 to-slate-950',
        iconClass: 'bg-rose-500/15 text-rose-300 ring-rose-400/20',
        valueClass: 'text-rose-200',
    },
    {
        title: 'Total Asset Value',
        value: formatShortCurrency(totalValue.value),
        subtitle: 'Original purchase cost before depreciation',
        icon: CircleDollarSign,
        badge: 'Finance',
        cardClass: 'from-cyan-950/90 via-slate-950 to-slate-950',
        iconClass: 'bg-cyan-500/15 text-cyan-300 ring-cyan-400/20',
        valueClass: 'text-cyan-200',
    },
])


const securityCards = computed(() => [
    { title: 'Today’s Logins', value: props.securityStats?.todaysLogins ?? 0, subtitle: 'Successful logins today', icon: Activity, class: 'text-emerald-300 bg-emerald-500/10' },
    { title: 'Failed Logins', value: props.securityStats?.failedLogins ?? 0, subtitle: 'Failed attempts today', icon: ShieldAlert, class: 'text-red-300 bg-red-500/10' },
    { title: 'Blocked Accounts', value: (props.securityStats?.inactiveBlocked ?? 0) + (props.securityStats?.expiredBlocked ?? 0), subtitle: 'Inactive/expired attempts', icon: AlertTriangle, class: 'text-amber-300 bg-amber-500/10' },
    { title: 'Activities Today', value: props.securityStats?.activitiesToday ?? 0, subtitle: 'Tracked system actions', icon: BarChart3, class: 'text-blue-300 bg-blue-500/10' },
    { title: 'Unread Alerts', value: props.securityStats?.unreadNotifications ?? 0, subtitle: 'Pending notifications', icon: CalendarClock, class: 'text-cyan-300 bg-cyan-500/10' },
    { title: 'Pending Approvals', value: props.securityStats?.pendingApprovals ?? 0, subtitle: 'Workflow requests', icon: BadgeCheck, class: 'text-indigo-300 bg-indigo-500/10' },
])

const lifecycleCards = computed(() => [
    {
        label: 'Disposed',
        value: disposedAssets.value,
        percentage: safePercent(disposedAssets.value, totalAssets.value),
        icon: Archive,
        class: 'text-red-300 bg-red-500/10 ring-red-500/20',
    },
    {
        label: 'Obsolete',
        value: obsoleteAssets.value,
        percentage: safePercent(obsoleteAssets.value, totalAssets.value),
        icon: ShieldAlert,
        class: 'text-orange-300 bg-orange-500/10 ring-orange-500/20',
    },
    {
        label: 'Under Maintenance',
        value: underMaintenanceAssets.value,
        percentage: safePercent(underMaintenanceAssets.value, totalAssets.value),
        icon: Wrench,
        class: 'text-amber-300 bg-amber-500/10 ring-amber-500/20',
    },
])

/* ================= CHART COLORS ================= */

const colors = [
    '#38bdf8',
    '#22c55e',
    '#f59e0b',
    '#ef4444',
    '#a855f7',
    '#14b8a6',
    '#f97316',
    '#ec4899',
    '#84cc16',
    '#6366f1',
    '#06b6d4',
    '#eab308',
]

const generateColors = (length: number) =>
    Array.from({ length }, (_, index) => colors[index % colors.length])

/* ================= TOOLTIP BASE ================= */

const baseTooltip = {
    backgroundColor: '#020617',
    borderColor: '#334155',
    borderWidth: 1,
    titleColor: '#ffffff',
    bodyColor: '#cbd5e1',
    padding: 12,
    displayColors: true,
}

/* ================= CHART OPTIONS ================= */

const barOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 900, easing: 'easeOutQuart' },
    plugins: {
        legend: { display: false },
        tooltip: {
            ...baseTooltip,
            callbacks: {
                label: (item) => `Total: ${formatNumber(item.raw)}`,
            },
        },
    },
    scales: {
        x: {
            ticks: {
                color: '#cbd5e1',
                autoSkip: true,
                maxRotation: 35,
                minRotation: 0,
                font: { size: 11 },
            },
            grid: { display: false },
            border: { display: false },
        },
        y: {
            beginAtZero: true,
            ticks: {
                color: '#94a3b8',
                precision: 0,
                font: { size: 11 },
            },
            grid: { color: 'rgba(148, 163, 184, 0.12)' },
            border: { display: false },
        },
    },
}

const assetNameBarOptions: ChartOptions<'bar'> = {
    ...barOptions,
    plugins: {
        ...barOptions.plugins,
        tooltip: {
            ...baseTooltip,
            callbacks: {
                title: (items) => {
                    const index = items[0]?.dataIndex ?? 0
                    return props.assetsByName?.[index]?.name ?? 'Unknown Asset'
                },
                label: (item) => {
                    const shortName = props.assetsByName?.[item.dataIndex]?.short
                    const shortLabel = shortName ? `Code: ${shortName}` : null
                    const totalLabel = `Total: ${formatNumber(item.raw)}`

                    return shortLabel ? [shortLabel, totalLabel] : totalLabel
                },
            },
        },
    },
}

const locationBarOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 900, easing: 'easeOutQuart' },
    plugins: {
        legend: { display: false },
        tooltip: {
            ...baseTooltip,
            callbacks: {
                title: (items) => {
                    const index = items[0]?.dataIndex ?? 0
                    return props.assetsByLocation?.[index]?.location ?? 'Unknown Location'
                },
                label: (item) => `Total: ${formatNumber(item.raw)}`,
            },
        },
    },
    scales: {
        x: {
            ticks: {
                color: '#cbd5e1',
                autoSkip: false,
                maxRotation: 0,
                minRotation: 0,
                font: { size: 10 },
                callback: function (value) {
                    const label = this.getLabelForValue(Number(value))
                    return label.length > 12 ? `${label.slice(0, 12)}…` : label
                },
            },
            grid: { display: false },
            border: { display: false },
        },
        y: {
            beginAtZero: true,
            ticks: {
                color: '#94a3b8',
                precision: 0,
                font: { size: 11 },
            },
            grid: { color: 'rgba(148, 163, 184, 0.12)' },
            border: { display: false },
        },
    },
}

const doughnutOptions: ChartOptions<'doughnut'> = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '72%',
    animation: { duration: 900, easing: 'easeOutQuart' },
    plugins: {
        legend: { display: false },
        tooltip: {
            ...baseTooltip,
            callbacks: {
                label: (item) => {
                    const value = Number(item.raw ?? 0)
                    const percentage = safePercent(value, totalAssets.value)

                    return `Total: ${formatNumber(value)} (${formatPercent(percentage)})`
                },
            },
        },
    },
}

const lineOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { labels: { color: '#cbd5e1', usePointStyle: true } },
        tooltip: {
            ...baseTooltip,
            callbacks: { label: (item) => item.dataset.label === 'Asset Value'
                ? `Asset Value: ${formatCurrency(Number(item.raw ?? 0))}`
                : `Asset Count: ${formatNumber(item.raw)}` },
        },
    },
    scales: {
        x: { ticks: { color: '#cbd5e1' }, grid: { display: false } },
        count: {
            type: 'linear', position: 'left', beginAtZero: true,
            title: { display: true, text: 'Number of assets', color: '#38bdf8' },
            ticks: { color: '#38bdf8', precision: 0 },
            grid: { color: 'rgba(148,163,184,0.12)' },
        },
        value: {
            type: 'linear', position: 'right', beginAtZero: true,
            title: { display: true, text: 'Purchase value (GH₵)', color: '#22c55e' },
            ticks: { color: '#22c55e', callback: (value) => {
                const amount = Number(value)
                return amount >= 1000000 ? `${(amount / 1000000).toFixed(1)}M`
                    : amount >= 1000 ? `${(amount / 1000).toFixed(1)}K` : formatNumber(amount)
            } },
            grid: { drawOnChartArea: false },
        },
    },
}

const statusChart = computed<ChartData<'doughnut'>>(() => ({
    labels: statusRows.value.map((row) => row.label),
    datasets: [
        {
            label: 'Assets',
            data: statusRows.value.map((row) => row.total),
            backgroundColor: generateColors(statusRows.value.length),
            borderColor: '#020617',
            borderWidth: 4,
            hoverOffset: 8,
        },
    ],
}))

const assetNameChart = computed<ChartData<'bar'>>(() => ({
    labels: (props.assetsByName ?? []).map((asset) => asset.short || asset.name),
    datasets: [
        {
            label: 'Assets',
            data: (props.assetsByName ?? []).map((asset) => Number(asset.total ?? 0)),
            backgroundColor: generateColors(props.assetsByName?.length ?? 0),
            borderRadius: 8,
            barPercentage: 0.68,
            categoryPercentage: 0.78,
        },
    ],
}))

const yearLineChart = computed<ChartData<'line'>>(() => ({
    labels: sortedYears.value.map((row) => String(row.year)),
    datasets: [
        {
            label: 'Asset Count',
            yAxisID: 'count',
            data: sortedYears.value.map((row) => row.total),
            borderColor: '#38bdf8',
            backgroundColor: 'rgba(56, 189, 248, 0.14)',
            pointBackgroundColor: '#38bdf8',
            pointBorderColor: '#020617',
            pointBorderWidth: 2,
            pointRadius: 4,
            tension: 0.38,
            cubicInterpolationMode: 'monotone',
            fill: false,
        },
        {
            label: 'Asset Value',
            yAxisID: 'value',
            data: sortedYears.value.map((row) => row.value),
            borderColor: '#22c55e',
            backgroundColor: 'rgba(34, 197, 94, 0.10)',
            pointBackgroundColor: '#22c55e',
            pointBorderColor: '#020617',
            pointBorderWidth: 2,
            pointRadius: 4,
            tension: 0.38,
            cubicInterpolationMode: 'monotone',
            fill: true,
        },
    ],
}))

const locationChart = computed<ChartData<'bar'>>(() => ({
    labels: (props.assetsByLocation ?? []).map((location) =>
        abbreviate(location.location),
    ),
    datasets: [
        {
            label: 'Assets',
            data: (props.assetsByLocation ?? []).map((location) => Number(location.total ?? 0)),
            backgroundColor: generateColors(props.assetsByLocation?.length ?? 0),
            borderRadius: 8,
            barPercentage: 0.68,
            categoryPercentage: 0.78,
        },
    ],
}))

const statusBarChart = computed<ChartData<'bar'>>(() => ({
    labels: statusRows.value.map((row) => row.label),
    datasets: [
        {
            label: 'Assets',
            data: statusRows.value.map((row) => row.total),
            backgroundColor: generateColors(statusRows.value.length),
            borderRadius: 8,
            barPercentage: 0.68,
            categoryPercentage: 0.78,
        },
    ],
}))
</script>

<template>
    <Head title="Asset Management Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-slate-950 text-slate-100">
            <div class="space-y-2 p-2 sm:p-3 lg:p-4">
                <!-- EXECUTIVE HEADER -->
                <section class="rounded-2xl border border-white/10 bg-linear-to-br from-slate-950 via-blue-950/55 to-slate-950 p-3 shadow-lg shadow-black/20">
                    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-2.5 py-1 text-[11px] font-semibold text-cyan-200">
                                    <BarChart3 class="h-3.5 w-3.5" />
                                    Asset Intelligence Dashboard
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-200">
                                    <BadgeCheck class="h-3.5 w-3.5" />
                                    {{ formatPercent(activeRate) }} Active Rate
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                    :class="expiringSoon > 0
                                        ? 'border-amber-400/20 bg-amber-400/10 text-amber-200'
                                        : 'border-slate-400/20 bg-slate-400/10 text-slate-200'"
                                >
                                    <AlertTriangle class="h-3.5 w-3.5" />
                                    {{ formatNumber(expiringSoon) }} Warranty Alert
                                </span>
                            </div>

                            <h1 class="mt-2 text-xl font-bold tracking-tight text-white sm:text-2xl lg:text-3xl">
                                Digital Asset Management Overview
                            </h1>

                            <p class="mt-1 max-w-4xl text-xs leading-5 text-slate-300">
                                Compact summary of asset value, lifecycle status, warranty exposure, location concentration, and acquisition trends across the institution.
                            </p>
                        </div>

                        <div class="grid min-w-full gap-2 sm:grid-cols-3 lg:min-w-105 lg:max-w-130">
                            <div class="rounded-xl border border-emerald-400/10 bg-emerald-500/10 px-3 py-2">
                                <p class="text-[11px] font-semibold text-emerald-200">Active</p>
                                <p class="text-lg font-bold text-white">{{ formatNumber(activeAssets) }}</p>
                            </div>
                            <div class="rounded-xl border border-amber-400/10 bg-amber-500/10 px-3 py-2">
                                <p class="text-[11px] font-semibold text-amber-200">Maintenance</p>
                                <p class="text-lg font-bold text-white">{{ formatNumber(underMaintenanceAssets) }}</p>
                            </div>
                            <div class="rounded-xl border border-red-400/10 bg-red-500/10 px-3 py-2">
                                <p class="text-[11px] font-semibold text-red-200">Risk Items</p>
                                <p class="text-lg font-bold text-white">{{ formatNumber(lifecycleRiskCount) }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- AI RESEARCH (only for users with the AI permissions) -->
                <Link
                    v-if="aiResearchPending !== null && aiResearchPending !== undefined"
                    :href="assetResearchIndex().url"
                    class="flex flex-col gap-2 rounded-xl border border-indigo-400/20 bg-indigo-500/10 px-3 py-2.5 transition hover:bg-indigo-500/20 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-2">
                        <Sparkles class="h-4 w-4 shrink-0 text-indigo-300" />
                        <div>
                            <p class="text-sm font-semibold text-white">AI Research</p>
                            <p class="text-xs text-indigo-200/80">
                                <template v-if="aiResearchPending > 0">
                                    {{ formatNumber(aiResearchPending) }} AI suggestion{{ aiResearchPending === 1 ? '' : 's' }} waiting for review
                                </template>
                                <template v-else>Look up specs and market prices for IT assets with AI</template>
                            </p>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-indigo-200">Open AI Research →</span>
                </Link>

                <!-- PRIMARY METRICS: AUTO-FIT REMOVES EMPTY GRID SPACE -->
                <section class="grid grid-cols-[repeat(auto-fit,minmax(160px,1fr))] gap-2">
                    <div class="rounded-xl border border-white/10 bg-slate-900/80 px-3 py-2 shadow-lg shadow-black/15">
                        <div class="flex items-center gap-2 text-[11px] font-semibold text-slate-400">
                            <PackageCheck class="h-4 w-4 text-blue-300" />
                            Total Inventory
                        </div>
                        <div class="mt-1 text-xl font-bold text-white">{{ formatNumber(totalAssets) }}</div>
                        <p class="mt-0.5 text-[10px] text-slate-500">Registered assets</p>
                    </div>

                    <div class="rounded-xl border border-white/10 bg-slate-900/80 px-3 py-2 shadow-lg shadow-black/15">
                        <div class="flex items-center gap-2 text-[11px] font-semibold text-slate-400">
                            <Banknote class="h-4 w-4 text-emerald-300" />
                            Total Asset Value
                        </div>
                        <div class="mt-1 text-xl font-bold text-emerald-200">{{ formatShortCurrency(totalValue) }}</div>
                        <p class="mt-0.5 text-[10px] text-slate-500">Original purchase cost</p>
                    </div>

                    <div class="rounded-xl border border-white/10 bg-slate-900/80 px-3 py-2 shadow-lg shadow-black/15">
                        <div class="flex items-center gap-2 text-[11px] font-semibold text-slate-400">
                            <CircleDollarSign class="h-4 w-4 text-cyan-300" />
                            Value After Depreciation
                        </div>
                        <div class="mt-1 text-xl font-bold text-cyan-200">{{ formatShortCurrency(depreciatedAssetValue) }}</div>
                        <p class="mt-0.5 text-[10px] text-slate-500">Useful assets: {{ formatNumber(depreciableAssetCount) }}</p>
                    </div>

                    <div class="rounded-xl border border-white/10 bg-slate-900/80 px-3 py-2 shadow-lg shadow-black/15">
                        <div class="flex items-center gap-2 text-[11px] font-semibold text-slate-400">
                            <TrendingUp class="h-4 w-4 text-cyan-300" />
                            Latest Year Growth
                        </div>
                        <div
                            class="mt-1 text-xl font-bold"
                            :class="latestYearGrowth >= 0 ? 'text-cyan-200' : 'text-red-200'"
                        >
                            {{ latestYearGrowth >= 0 ? '+' : '' }}{{ formatPercent(latestYearGrowth) }}
                        </div>
                        <p class="mt-0.5 text-[10px] text-slate-500">Acquisition change</p>
                    </div>

                    <div
                        v-for="item in fixedYearCards"
                        :key="item.title"
                        class="rounded-xl border border-white/10 bg-slate-900/80 px-3 py-2 shadow-lg shadow-black/15"
                    >
                        <div class="flex items-center gap-2 truncate text-[11px] font-semibold text-slate-400">
                            <CalendarClock class="h-4 w-4 shrink-0 text-cyan-300" />
                            <span class="truncate">{{ item.title }}</span>
                        </div>
                        <div class="mt-1 text-lg font-bold text-cyan-200">{{ formatShortCurrency(item.cost) }}</div>
                        <p class="mt-0.5 text-[10px] text-slate-500">Qty: {{ formatNumber(item.quantity) }}</p>
                    </div>
                </section>

                <!-- QUICK ASSET INSIGHTS -->
                <section class="grid grid-cols-[repeat(auto-fit,minmax(160px,1fr))] gap-2">
                    <div class="rounded-xl border border-emerald-400/10 bg-emerald-500/10 px-3 py-2 flex items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold text-emerald-200">Useful Assets</p>
                        <p class="text-sm font-bold text-white">{{ formatNumber(depreciableAssetCount) }}</p>
                    </div>
                    <div class="rounded-xl border border-amber-400/10 bg-amber-500/10 px-3 py-2 flex items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold text-amber-200">Under Maintenance</p>
                        <p class="text-sm font-bold text-white">{{ formatNumber(underMaintenanceAssets) }}</p>
                    </div>
                    <div class="rounded-xl border border-red-400/10 bg-red-500/10 px-3 py-2 flex items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold text-red-200">Disposed</p>
                        <p class="text-sm font-bold text-white">{{ formatNumber(disposedAssets) }}</p>
                    </div>
                    <div class="rounded-xl border border-orange-400/10 bg-orange-500/10 px-3 py-2 flex items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold text-orange-200">Obsolete</p>
                        <p class="text-sm font-bold text-white">{{ formatNumber(obsoleteAssets) }}</p>
                    </div>
                    <div class="rounded-xl border border-cyan-400/10 bg-cyan-500/10 px-3 py-2 flex items-center justify-between gap-2">
                        <p class="text-[11px] font-semibold text-cyan-200">Warranty Alerts</p>
                        <p class="text-sm font-bold text-white">{{ formatNumber(expiringSoon) }}</p>
                    </div>
                </section>

                <!-- SECURITY DASHBOARD CARDS -->
                <section class="grid grid-cols-[repeat(auto-fit,minmax(160px,1fr))] gap-2">
                    <div
                        v-for="card in securityCards"
                        :key="card.title"
                        class="rounded-xl border border-white/10 bg-slate-900/70 px-3 py-2 shadow-md shadow-black/10"
                    >
                        <div class="flex items-center gap-2" :title="card.subtitle">
                            <div class="shrink-0 rounded-lg p-1.5" :class="card.class">
                                <component :is="card.icon" class="h-4 w-4" />
                            </div>
                            <p class="min-w-0 flex-1 text-[11px] font-semibold leading-tight text-slate-400">{{ card.title }}</p>
                            <h3 class="shrink-0 text-xl font-bold tabular-nums text-white">{{ formatNumber(card.value) }}</h3>
                        </div>
                    </div>
                </section>

                <!-- OPERATIONS ROW -->
                <section class="grid gap-2 xl:grid-cols-[1.15fr_0.95fr_0.95fr_0.95fr]">
                    <div
                        class="rounded-xl border px-3 py-2 shadow-lg"
                        :class="expiringSoon > 0
                            ? 'border-amber-400/20 bg-amber-950/25 shadow-amber-950/10'
                            : 'border-emerald-400/20 bg-emerald-950/20 shadow-emerald-950/10'"
                    >
                        <div class="flex items-start gap-2">
                            <div
                                class="shrink-0 rounded-lg p-1.5 ring-1"
                                :class="expiringSoon > 0
                                    ? 'bg-amber-400/10 text-amber-200 ring-amber-400/20'
                                    : 'bg-emerald-400/10 text-emerald-200 ring-emerald-400/20'"
                            >
                                <AlertTriangle v-if="expiringSoon > 0" class="h-4 w-4" />
                                <BadgeCheck v-else class="h-4 w-4" />
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-xs font-semibold text-white">Warranty Monitoring</h2>
                                <p class="mt-0.5 text-[11px] leading-4 text-slate-300">
                                    {{ formatNumber(expiringSoon) }} active asset(s) have warranties expiring within the next 90 days.
                                </p>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <span class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] text-emerald-200">
                                        Warranty scope: Active assets only
                                    </span>
                                    <span class="rounded-full bg-white/5 px-2 py-0.5 text-[10px] text-slate-300">
                                        Active: {{ formatNumber(activeAssets) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-for="item in lifecycleCards"
                        :key="item.label"
                        class="rounded-xl border border-white/10 bg-slate-900/80 px-3 py-2 shadow-lg shadow-black/20"
                    >
                        <div class="flex items-center gap-2">
                            <div class="shrink-0 rounded-lg p-1.5 ring-1" :class="item.class">
                                <component :is="item.icon" class="h-4 w-4" />
                            </div>
                            <div class="text-xl font-bold tabular-nums text-white">{{ formatNumber(item.value) }}</div>
                            <span class="ml-auto shrink-0 text-[11px] font-semibold text-slate-300">{{ formatPercent(item.percentage) }}</span>
                        </div>
                        <div class="mt-1 text-[11px] leading-tight text-slate-400">{{ item.label }}</div>
                        <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-slate-800">
                            <div class="h-full rounded-full bg-current" :class="item.class" :style="{ width: `${Math.min(item.percentage, 100)}%` }" />
                        </div>
                    </div>
                </section>

                <!-- ANALYTICS -->
                <section class="grid gap-2 xl:grid-cols-[0.8fr_1.2fr]">
                    <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-3 shadow-lg shadow-black/20">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Lifecycle</p>
                                <h2 class="text-base font-semibold text-white">Assets by Status</h2>
                            </div>
                            <div class="rounded-xl bg-blue-500/10 p-2 text-blue-300 ring-1 ring-blue-400/20">
                                <ShieldAlert class="h-5 w-5" />
                            </div>
                        </div>
                        <div class="relative h-52">
                            <Doughnut :data="statusChart" :options="doughnutOptions" />
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                <div class="text-3xl font-bold text-white">{{ formatNumber(totalAssets) }}</div>
                                <div class="text-xs text-slate-400">Total Assets</div>
                            </div>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <div
                                v-for="row in statusRows"
                                :key="row.status"
                                class="flex items-center justify-between gap-3 rounded-xl bg-white/4 px-3 py-2"
                            >
                                <div class="min-w-0">
                                    <div class="truncate text-xs font-medium text-slate-200">{{ row.label }}</div>
                                    <div class="text-[11px] text-slate-500">{{ formatPercent(row.percentage) }}</div>
                                </div>
                                <div class="text-xs font-semibold text-white">{{ formatNumber(row.total) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex min-h-0 flex-col rounded-2xl border border-white/10 bg-slate-900/80 p-3 shadow-lg shadow-black/20">
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Acquisition Trend</p>
                                <h2 class="text-base font-semibold text-white">Assets and Value by Purchase Year</h2>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full bg-blue-500/10 px-3 py-1 font-medium text-blue-200">Last 15 years - {{ sortedYears.length ? `${sortedYears[0].year}–${sortedYears[sortedYears.length - 1].year}` : 'No purchase dates' }}</span>
                                <span class="rounded-full bg-emerald-500/10 px-3 py-1 font-medium text-emerald-200">{{ formatCurrency(sortedYears.reduce((sum, row) => sum + row.value, 0)) }}</span>
                            </div>
                        </div>
                        <div class="relative h-72 min-h-0 xl:h-auto xl:flex-1">
                            <Line :data="yearLineChart" :options="lineOptions" />
                        </div>
                    </div>
                </section>

                <!-- CHART GRID -->
                <section class="grid gap-2 xl:grid-cols-2">
                    <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-3 shadow-lg shadow-black/20">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Asset Types</p>
                                <h2 class="text-base font-semibold text-white">Top Asset Categories</h2>
                            </div>
                            <span class="rounded-full bg-purple-500/10 px-3 py-1 text-xs font-medium text-purple-200">Top {{ props.assetsByName?.length ?? 0 }}</span>
                        </div>
                        <div class="h-60">
                            <Bar :data="assetNameChart" :options="assetNameBarOptions" />
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-3 shadow-lg shadow-black/20">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Locations</p>
                                <h2 class="text-base font-semibold text-white">Top Asset Locations</h2>
                            </div>
                            <span class="rounded-full bg-cyan-500/10 px-3 py-1 text-xs font-medium text-cyan-200">Top {{ props.assetsByLocation?.length ?? 0 }}</span>
                        </div>
                        <div class="h-60">
                            <Bar :data="locationChart" :options="locationBarOptions" />
                        </div>
                    </div>
                </section>

                <!-- BOTTOM INSIGHTS -->
                <section class="grid gap-2 xl:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-3 shadow-lg shadow-black/20">
                        <div class="flex items-center gap-3">
                            <div class="rounded-xl bg-blue-500/10 p-2 text-blue-300 ring-1 ring-blue-400/20">
                                <Building2 class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Highest Location</p>
                                <h3 class="text-base font-semibold text-white">{{ topLocation?.location ?? 'No location data' }}</h3>
                            </div>
                        </div>
                        <div class="mt-3 text-3xl font-bold text-white">{{ formatNumber(topLocation?.total ?? 0) }}</div>
                        <p class="mt-1 text-sm text-slate-400">Asset(s) recorded under this location.</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-3 shadow-lg shadow-black/20">
                        <div class="flex items-center gap-3">
                            <div class="rounded-xl bg-purple-500/10 p-2 text-purple-300 ring-1 ring-purple-400/20">
                                <Boxes class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Most Common Asset</p>
                                <h3 class="text-base font-semibold text-white">{{ topAssetName?.name ?? 'No asset data' }}</h3>
                            </div>
                        </div>
                        <div class="mt-3 text-3xl font-bold text-white">{{ formatNumber(topAssetName?.total ?? 0) }}</div>
                        <p class="mt-1 text-sm text-slate-400">Highest recorded asset category.</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-3 shadow-lg shadow-black/20">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Status Count</p>
                                <h2 class="text-base font-semibold text-white">Status Comparison</h2>
                            </div>
                            <MapPin class="h-5 w-5 text-slate-400" />
                        </div>
                        <div class="h-44">
                            <Bar :data="statusBarChart" :options="barOptions" />
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
