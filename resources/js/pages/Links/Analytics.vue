<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { ref, onMounted, onBeforeUnmount } from 'vue'
import Chart from 'chart.js/auto'

/* ================= TYPES ================= */
type CountItem = { total: number }
type DateItem = { date: string; total: number }

interface Props {
    totals: any[]
    dailyScans: DateItem[]
    devices: any[]
    countries: any[]
    regions: any[]
    cities: any[]
    linkScanHistory: any[]   // ⭐ FULL HISTORY
}


const props = defineProps<Props>()

/* ================= CANVAS REFS ================= */
const lineRef = ref<HTMLCanvasElement | null>(null)
const topRef = ref<HTMLCanvasElement | null>(null)
const deviceRef = ref<HTMLCanvasElement | null>(null)
const countryRef = ref<HTMLCanvasElement | null>(null)
const regionRef = ref<HTMLCanvasElement | null>(null)
const cityRef = ref<HTMLCanvasElement | null>(null)

/* ================= CHART STORAGE ================= */
let charts: Chart[] = []

/* ================= COLORS ================= */
const colors = [
    '#60a5fa', '#34d399', '#fbbf24', '#f87171', '#a78bfa',
    '#22d3ee', '#fb7185', '#4ade80', '#facc15', '#c084fc', '#38bdf8'
]

/* ================= AUTO ABBREVIATION ================= */
function abbreviate(label: string): string {
    if (!label || label.toLowerCase() === 'unknown') return 'UNK'
    const words = label.trim().split(/\s+/)
    if (words.length === 1) return words[0].substring(0, 3).toUpperCase()
    return words.map(w => w[0]).join('').toUpperCase()
}

/* ================= LINE CHART ================= */
function createLineChart() {
    if (!lineRef.value) return
    const ctx = lineRef.value.getContext('2d')
    if (!ctx) return

    const gradient = ctx.createLinearGradient(0, 0, 0, 250)
    gradient.addColorStop(0, 'rgba(59,130,246,0.35)')
    gradient.addColorStop(1, 'rgba(59,130,246,0.02)')

    charts.push(
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: props.dailyScans.map(d => d.date),
                datasets: [{
                    label: 'Scans',
                    data: props.dailyScans.map(d => d.total),
                    tension: 0.45,
                    fill: true,
                    backgroundColor: gradient,
                    borderColor: '#60a5fa',
                    borderWidth: 2,
                    pointRadius: 3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(148,163,184,0.15)' },
                    },
                },
                plugins: {
                    legend: { display: true },
                    tooltip: { mode: 'index', intersect: false },
                },
            },
        })
    )
}

/* ================= BAR CHART BUILDER ================= */
function createVerticalBar(
    el: HTMLCanvasElement | null,
    rawLabels: string[],
    rawData: number[]
) {
    if (!el) return

    const MAX_BARS = 5

    const pairs = rawLabels.map((l, i) => ({ label: l, value: rawData[i] }))
    pairs.sort((a, b) => b.value - a.value)

    const top = pairs.slice(0, MAX_BARS)
    const others = pairs.slice(MAX_BARS)

    if (others.length) {
        top.push({
            label: 'Others',
            value: others.reduce((s, x) => s + x.value, 0),
        })
    }

    const labels = top.map(p => p.label)
    const data = top.map(p => p.value)
    const total = data.reduce((a, b) => a + b, 0)

    charts.push(
        new Chart(el, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: labels.map((_, i) => colors[i % colors.length]),
                    borderRadius: 10,
                    barThickness: 26,
                }],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const v = ctx.raw as number
                                const pct = ((v / total) * 100).toFixed(1)
                                return `${ctx.label}: ${v} scans (${pct}%)`
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#cbd5f5',
                            callback: function (value) {
                                const label = this.getLabelForValue(value as number)
                                return abbreviate(label)
                            },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: '#94a3b8' },
                        grid: { color: 'rgba(255,255,255,0.06)' },
                    },
                },
            },
        })
    )
}

/* ================= INIT ================= */
onMounted(() => {
    createLineChart()

    createVerticalBar(
        topRef.value,
        props.totals.map(l => l.name),
        props.totals.map(l => l.total_scans)
    )

    createVerticalBar(
        deviceRef.value,
        props.devices.map(d => d.device),
        props.devices.map(d => d.total)
    )

    createVerticalBar(
        countryRef.value,
        props.countries.map(c => c.country),
        props.countries.map(c => c.total)
    )

    createVerticalBar(
        regionRef.value,
        props.regions.map(r => r.region),
        props.regions.map(r => r.total)
    )

    createVerticalBar(
        cityRef.value,
        props.cities.map(c => c.city),
        props.cities.map(c => c.total)
    )
})

onBeforeUnmount(() => {
    charts.forEach(chart => chart.destroy())
})
</script>

<template>

    <Head title="QR Analytics Dashboard" />

    <AppLayout>
        <div class="p-6 space-y-8 text-white">

            <!-- HEADER -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">QR Analytics Dashboard</h1>
                <Link href="/links" class="bg-slate-700 px-4 py-2 rounded-lg text-sm hover:bg-slate-600">
                    Back to Links
                </Link>
            </div>

            <!-- LINE CHART -->
            <div class="bg-slate-800 rounded-2xl p-6 shadow-lg">
                <h2 class="text-lg font-semibold mb-4">Scan activities (last 30 days)</h2>
                <div class="relative h-[320px] w-full">
                    <canvas ref="lineRef"></canvas>
                </div>
            </div>

            <!-- FIVE CARDS IN ONE ROW -->
            <div class="grid gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">

                <div class="bg-slate-800 rounded-2xl p-5 text-center">
                    <h3 class="font-semibold mb-3">Top Scanned Names</h3>
                    <div class="h-[220px] flex items-center justify-center">
                        <canvas ref="topRef"></canvas>
                    </div>
                </div>

                <div class="bg-slate-800 rounded-2xl p-5 text-center">
                    <h3 class="font-semibold mb-3">Operating System</h3>
                    <div class="h-[220px] flex items-center justify-center">
                        <canvas ref="deviceRef"></canvas>
                    </div>
                </div>

                <div class="bg-slate-800 rounded-2xl p-5 text-center">
                    <h3 class="font-semibold mb-3">Scans by Country</h3>
                    <div class="h-[220px] flex items-center justify-center">
                        <canvas ref="countryRef"></canvas>
                    </div>
                </div>

                <div class="bg-slate-800 rounded-2xl p-5 text-center">
                    <h3 class="font-semibold mb-3">Scans by Region</h3>
                    <div class="h-[220px] flex items-center justify-center">
                        <canvas ref="regionRef"></canvas>
                    </div>
                </div>

                <div class="bg-slate-800 rounded-2xl p-5 text-center">
                    <h3 class="font-semibold mb-3">Scans by Town/City</h3>
                    <div class="h-[220px] flex items-center justify-center">
                        <canvas ref="cityRef"></canvas>
                    </div>
                </div>

            </div>
            <!-- ================= LINKS TABLE ================= -->
            <div class="bg-slate-800 rounded-2xl overflow-hidden shadow-lg">
                <div class="px-5 py-4 border-b border-slate-700 font-semibold">
                    All QR Links by Scan Period
                </div>

                <div class="max-h-[420px] overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-700 text-slate-200 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left">#</th>
                                <th class="px-4 py-3 text-left">Link Name</th>
                                <th class="px-4 py-3 text-left">Daily</th>
                                <th class="px-4 py-3 text-left">Weekly</th>
                                <th class="px-4 py-3 text-left">Monthly</th>
                                <th class="px-4 py-3 text-left">Yearly</th>
                                <th class="px-4 py-3 text-left">Total</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="(link, i) in props.totals" :key="link.id"
                                class="border-t border-slate-700 hover:bg-slate-700/40 transition">
                                <td class="px-4 py-3">{{ i + 1 }}</td>
                                <td class="px-4 py-3 font-medium">{{ link.name }}</td>
                                <td class="px-4 py-3">{{ link.daily_scans }}</td>
                                <td class="px-4 py-3">{{ link.weekly_scans }}</td>
                                <td class="px-4 py-3">{{ link.monthly_scans }}</td>
                                <td class="px-4 py-3">{{ link.yearly_scans }}</td>
                                <td class="px-4 py-3 font-semibold">{{ link.total_scans }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>


            <!-- ================= ALL SCAN HISTORY ================= -->
            <div class="bg-slate-800 rounded-2xl overflow-hidden shadow-lg">
                <div class="px-5 py-4 border-b border-slate-700 font-semibold">
                    Link Scan History (All Scan Records)
                </div>

                <div class="max-h-[420px] overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-700 text-slate-200 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left">#</th>

                                <th class="px-4 py-3 text-left">Link Name</th>
                                <th class="px-4 py-3 text-left">Time</th>
                                <th class="px-4 py-3 text-left">City</th>
                                <th class="px-4 py-3 text-left">Region</th>
                                <th class="px-4 py-3 text-left">Country</th>
                                <th class="px-4 py-3 text-left">Device</th>
                                <th class="px-4 py-3 text-left">Browser</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="(s, i) in props.linkScanHistory" :key="s.scan_id"
                                class="border-t border-slate-700 hover:bg-slate-700/40 transition">
                                <td class="px-4 py-3">{{ i + 1 }}</td>
                                <td class="px-4 py-3 font-medium">{{ s.name }}</td>
                                <td class="px-4 py-3">{{ s.scan_time }}</td>
                                <td class="px-4 py-3">{{ s.city }}</td>
                                <td class="px-4 py-3">{{ s.region }}</td>
                                <td class="px-4 py-3">{{ s.country }}</td>
                                <td class="px-4 py-3">{{ s.device }}</td>
                                <td class="px-4 py-3">{{ s.browser }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>


        </div>
    </AppLayout>
</template>
