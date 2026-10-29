<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import Chart from 'chart.js/auto'

/* ================= TYPES ================= */
type DateItem = { date: string; total: number }


interface Props {
  link: {
    id: number
    name: string
    total_scans: number
  }
  dailyScans: DateItem[]
  devices: { device: string; total: number }[]
  countries: { country: string; total: number }[]
  regions: { region: string; total: number }[]
  cities: { city: string; total: number }[]
  linkScanHistory: any[]
  totals: any[]
}

const props = defineProps<Props>()

/* ================= CANVAS REFS ================= */
const lineRef = ref<HTMLCanvasElement | null>(null)
const deviceRef = ref<HTMLCanvasElement | null>(null)
const countryRef = ref<HTMLCanvasElement | null>(null)
const regionRef = ref<HTMLCanvasElement | null>(null)
const cityRef = ref<HTMLCanvasElement | null>(null)

/* ================= CHART STORAGE ================= */
let charts: Chart[] = []

/* ================= COLORS ================= */
const colors = [
  '#60a5fa', '#34d399', '#fbbf24',
  '#f87171', '#a78bfa', '#22d3ee'
]

/* ================= ABBREVIATION ================= */
function abbreviate(label: string): string {
  if (!label) return 'UNK'

  const cleaned = label.trim()

  if (!cleaned.includes(' ')) {
    return cleaned.substring(0, 3).toUpperCase()
  }

  return cleaned
    .split(' ')
    .map(word => word.charAt(0))
    .join('')
    .toUpperCase()
}

/* ================= SAFE DATE FORMAT ================= */
function formatDateLabel(date: string) {
  const d = new Date(date)
  return d.toLocaleDateString()
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
        labels: props.dailyScans.map(d => formatDateLabel(d.date)),
        datasets: [{
          label: 'Scans',
          data: props.dailyScans.map(d => d.total), // zero values preserved
          tension: 0.35,
          fill: true,
          backgroundColor: gradient,
          borderColor: '#60a5fa',
          borderWidth: 2,
          pointRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0 }
          }
        }
      }
    })
  )
}

/* ================= BAR CHART BUILDER ================= */
function createBar(
  el: HTMLCanvasElement | null,
  labels: string[],
  data: number[]
) {
  if (!el) return

  charts.push(
    new Chart(el, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: labels.map((_, i) => colors[i % colors.length]),
          borderRadius: 8
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: {
            ticks: {
              callback: function (value) {
                const label = this.getLabelForValue(value as number)
                return abbreviate(label)
              }
            }
          },
          y: {
            beginAtZero: true,
            ticks: { precision: 0 }
          }
        }
      }
    })
  )
}

/* ================= INIT ================= */
onMounted(async () => {
  await nextTick()

  createLineChart()

  createBar(
    deviceRef.value,
    props.devices.map(d => d.device || 'Unknown'),
    props.devices.map(d => d.total)
  )

  createBar(
    countryRef.value,
    props.countries.map(c => c.country || 'Unknown'),
    props.countries.map(c => c.total)
  )

  createBar(
    regionRef.value,
    props.regions.map(r => r.region || 'Unknown'),
    props.regions.map(r => r.total)
  )

  createBar(
    cityRef.value,
    props.cities.map(c => c.city || 'Unknown'),
    props.cities.map(c => c.total)
  )
})

/* ================= CLEANUP ================= */
onBeforeUnmount(() => {
  charts.forEach(c => c.destroy())
  charts = []
})
</script>

<template>
  <Head :title="`QR Analytics — ${link.name}`" />

  <AppLayout>
    <div class="p-6 space-y-8 text-white">

      <!-- HEADER -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">
            QR Analytics — {{ link.name }}
          </h1>
          <p class="text-slate-400 text-sm mt-1">
            Total Scans: {{ link.total_scans }}
          </p>
        </div>

        <Link
          href="/links"
          class="bg-slate-700 px-4 py-2 rounded-lg text-sm hover:bg-slate-600"
        >
          Back to Links
        </Link>
      </div>

      <!-- LINE CHART -->
      <div class="bg-slate-800 rounded-2xl p-6">
        <h2 class="font-semibold mb-4">Scan Activity (All Time)</h2>
        <div class="h-[300px]">
          <canvas ref="lineRef"></canvas>
        </div>
      </div>

      <!-- BAR CHART GRID -->
      <div class="grid gap-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-4">

        <div class="bg-slate-800 rounded-2xl p-5 text-center">
          <h3 class="font-semibold mb-3">Operating System</h3>
          <div class="h-[220px]">
            <canvas ref="deviceRef"></canvas>
          </div>
        </div>

        <div class="bg-slate-800 rounded-2xl p-5 text-center">
          <h3 class="font-semibold mb-3">Country</h3>
          <div class="h-[220px]">
            <canvas ref="countryRef"></canvas>
          </div>
        </div>

        <div class="bg-slate-800 rounded-2xl p-5 text-center">
          <h3 class="font-semibold mb-3">Region</h3>
          <div class="h-[220px]">
            <canvas ref="regionRef"></canvas>
          </div>
        </div>

        <div class="bg-slate-800 rounded-2xl p-5 text-center">
          <h3 class="font-semibold mb-3">City</h3>
          <div class="h-[220px]">
            <canvas ref="cityRef"></canvas>
          </div>
        </div>

      </div>

      <!-- PERIOD SUMMARY TABLE -->
      <div class="bg-slate-800 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700 font-semibold">
          Scan Summary
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
              <tr
                v-for="(item, i) in totals"
                :key="item.id"
                class="border-t border-slate-700 hover:bg-slate-700/40 transition"
              >
                <td class="px-4 py-3">{{ i + 1 }}</td>
                <td class="px-4 py-3 font-medium">{{ item.name }}</td>
                <td class="px-4 py-3">{{ item.daily_scans }}</td>
                <td class="px-4 py-3">{{ item.weekly_scans }}</td>
                <td class="px-4 py-3">{{ item.monthly_scans }}</td>
                <td class="px-4 py-3">{{ item.yearly_scans }}</td>
                <td class="px-4 py-3 font-semibold">
                  {{ item.total_scans }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
            <!-- SCAN HISTORY TABLE -->
      <div class="bg-slate-800 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700 font-semibold">
          Scan History
        </div>

        <div class="max-h-[420px] overflow-y-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-700 text-slate-200 sticky top-0">
              <tr>
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Time</th>
                <th class="px-4 py-3 text-left">City</th>
                <th class="px-4 py-3 text-left">Region</th>
                <th class="px-4 py-3 text-left">Country</th>
                <th class="px-4 py-3 text-left">Device</th>
                <th class="px-4 py-3 text-left">Browser</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="(s, i) in linkScanHistory"
                :key="i"
                class="border-t border-slate-700 hover:bg-slate-700/40"
              >
                <td class="px-4 py-3">{{ i + 1 }}</td>
                <td class="px-4 py-3">{{ s.scan_time }}</td>
                <td class="px-4 py-3">{{ s.city }}</td>
                <td class="px-4 py-3">{{ s.region }}</td>
                <td class="px-4 py-3">{{ s.country }}</td>
                <td class="px-4 py-3">{{ s.device }}</td>
                <td class="px-4 py-3">{{ s.browser }}</td>
              </tr>

              <tr v-if="!linkScanHistory.length">
                <td colspan="7" class="px-4 py-6 text-center text-slate-400">
                  No scan history found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AppLayout>
</template>
