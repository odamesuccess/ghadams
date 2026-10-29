<script setup lang="ts">
/* =======================
   Imports
======================= */
import { h, ref, watch } from 'vue'
import type { ColumnDef } from '@tanstack/vue-table'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import DataTable from '@/components/DataTable.vue'
import type { BreadcrumbItem } from '@/types'
import { can } from '@/utils/permissions'
import { confirmDelete } from '@/utils/sweetalert'

/* =======================
   Types
======================= */
interface Organization {
    organizations_name: string
    organization_abbreviation: string
}

interface Unit {
    id: number
    name: string
    locationname: string

    location_abbreviation: string
    unit_abbreviation: string
    organization_abbreviation?: string | null
    email?: string | null
    phonenumber?: string | null
    updated_at: string
    organization?: Organization | null
}

/* =======================
   Props
======================= */
const props = defineProps<{
    units: {
        data: Unit[]
    }
    filters: {
        search?: string
    }
}>()

/* =======================
   Search (Debounced)
======================= */
const search = ref(props.filters?.search ?? '')
let searchTimeout: number | undefined

watch(search, value => {
    clearTimeout(searchTimeout)
    searchTimeout = window.setTimeout(() => {
        router.get(
            '/units',
            { search: value },
            {
                preserveState: true,
                replace: true,
            }
        )
    }, 400)
})

/* =======================
   Helpers
======================= */
const formatDate = (value: string) =>
    new Date(value).toLocaleString()

/* =======================
   Table Columns
======================= */
const columns: ColumnDef<Unit>[] = [

    {
        header: 'Organization',
        accessorFn: row =>
            row.organization
                ? `${row.organization.organization_abbreviation} — ${row.organization.organizations_name}`
                : '—',
    },
    {
        header: 'Unit Name',
        accessorKey: 'name',
    },

    /* Unit Code */
    {
        header: 'Unit Abbreviation',
        accessorKey: 'unit_abbreviation',
        cell: ({ getValue }) =>
            h(
                'span',
                {
                    class:
                        'rounded bg-slate-700 px-2 py-1 text-xs font-semibold text-slate-200',
                },
                getValue() as string
            ),
    },



    {
        header: 'Location Name',
        accessorKey: 'locationname',
        cell: ({ getValue }) => getValue() ?? '—',
    },
    {
        header: 'Location Abbreviation',
        accessorKey: 'location_abbreviation',
        cell: ({ getValue }) => getValue() ?? '—',
    },
    {
        header: 'Phone',
        accessorKey: 'phonenumber',
        cell: ({ getValue }) => getValue() ?? '—',
    },

    {
        header: 'Updated',
        accessorKey: 'updated_at',
        cell: ({ getValue }) => formatDate(getValue() as string),
    },

    /* Actions */
    {
        header: 'Actions',
        id: 'actions',
        cell: ({ row }) => {
            const unit = row.original
            const actions = []

            if (can('units.view')) {
                actions.push(
                    h(
                        Link,
                        {
                            href: `/units/${unit.id}`,
                            class:
                                'rounded bg-slate-700 px-2 py-1 text-xs text-white hover:bg-slate-600',
                        },
                        'View'
                    )
                )
            }

            if (can('units.edit')) {
                actions.push(
                    h(
                        Link,
                        {
                            href: `/units/${unit.id}/edit`,
                            class:
                                'rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700',
                        },
                        'Edit'
                    )
                )
            }

            if (can('units.delete')) {
                actions.push(
                    h(
                        'button',
                        {
                            class:
                                'rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700',
                            onClick: async () => {
                                const confirmed = await confirmDelete({
                                    title: 'Delete Unit',
                                    text: `Are you sure you want to delete ${unit.name}?`,
                                })
                                if (confirmed) {
                                    router.delete(`/units/${unit.id}`)
                                }
                            },
                        },
                        'Delete'
                    )
                )
            }

            return h(
                'div',
                { class: 'flex gap-2 justify-end' },
                actions.length ? actions : '—'
            )
        },
    },
]

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Units Management', href: '/units' },
]
</script>


<template>

    <Head title="Units Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Action bar -->
        <div class="mt-2 flex items-center justify-between px-6 gap-4">
            <input v-model="search" type="text" placeholder="Search Units..."
                class="w-full max-w-sm rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600" />

            <Link v-if="can('units.create')" href="/units/create"
                class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                Create Unit
            </Link>
        </div>

        <!-- Table -->
        <div class="mx-6 mt-4 overflow-x-auto rounded-lg border border-slate-700">
            <DataTable :data="units.data" :columns="columns" />
        </div>
    </AppLayout>
</template>
