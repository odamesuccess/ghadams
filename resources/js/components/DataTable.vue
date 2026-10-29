<script setup lang="ts" generic="TData">
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue'

import {
    useVueTable,
    getCoreRowModel,
    getPaginationRowModel,
    FlexRender,
    type ColumnDef,
} from '@tanstack/vue-table'

import {
    Table,
    TableHeader,
    TableRow,
    TableHead,
    TableBody,
    TableCell,
} from '@/components/ui/table'

const props = withDefaults(
    defineProps<{
        data: TData[]
        columns: ColumnDef<TData>[]
        /**
         * Deprecated: shared DataTable search is intentionally disabled.
         * Page-level/top search and filters are the only supported search UI.
         */
        searchable?: boolean
        fillHeight?: boolean
        autoPageSize?: boolean
    }>(),
    {
        searchable: false,
        fillHeight: true,
        autoPageSize: true,
    },
)

/* =========================
   SEARCH POLICY
========================= */

/*
 * GhanaDAMS uses page-level/top search and filter controls.
 * This shared table intentionally has no internal/global search box.
 * The legacy `searchable` prop is retained only for backward compatibility
 * with existing page markup and is deliberately ignored here.
 */

/* =========================
   PAGINATION STATE
========================= */

const pageSize = ref(10)
const pageIndex = ref(0)

const pageSizeSelection =
    ref<'auto' | number>(
        props.autoPageSize
            ? 'auto'
            : 10,
    )

const rootRef =
    ref<HTMLElement | null>(null)

let resizeObserver:
    | ResizeObserver
    | null = null

let autoSizeRaf:
    | number
    | null = null

const table = useVueTable({
    get data() {
        return props.data
    },

    get columns() {
        return props.columns
    },

    state: {
        get pagination() {
            return {
                pageIndex:
                    pageIndex.value,
                pageSize:
                    pageSize.value,
            }
        },
    },


    onPaginationChange: (
        updater,
    ) => {
        const next =
            typeof updater ===
            'function'
                ? updater({
                      pageIndex:
                          pageIndex.value,
                      pageSize:
                          pageSize.value,
                  })
                : updater

        pageIndex.value =
            next.pageIndex

        pageSize.value =
            next.pageSize
    },

    getCoreRowModel:
        getCoreRowModel(),

    getPaginationRowModel:
        getPaginationRowModel(),
})

/* =========================
   DYNAMIC ROW COUNT
========================= */

function calculateAutoPageSize() {
    if (
        !props.fillHeight
        || !props.autoPageSize
        || pageSizeSelection.value
            !== 'auto'
        || !rootRef.value
    ) {
        return
    }

    const root =
        rootRef.value

    const controls =
        root.querySelector(
            '[data-dt-controls]',
        ) as HTMLElement | null

    const pagination =
        root.querySelector(
            '[data-dt-pagination]',
        ) as HTMLElement | null

    const thead =
        root.querySelector(
            'thead',
        ) as HTMLElement | null

    const sampleRow =
        root.querySelector(
            'tbody tr',
        ) as HTMLElement | null

    const controlsHeight =
        controls
            ?.getBoundingClientRect()
            .height
        ?? 0

    const paginationHeight =
        pagination
            ?.getBoundingClientRect()
            .height
        ?? 0

    const headerHeight =
        thead
            ?.getBoundingClientRect()
            .height
        ?? 42

    const measuredRowHeight =
        sampleRow
            ?.getBoundingClientRect()
            .height
        ?? 44

    /*
     * Keep row density normal. A minimum estimate of
     * 40px prevents excessive row counts when an empty
     * or unusually short row is measured.
     */
    const rowHeight =
        Math.max(
            40,
            measuredRowHeight,
        )

    const bodyHeight =
        root.clientHeight
        - controlsHeight
        - paginationHeight
        - headerHeight
        - 4

    const desired =
        Math.max(
            5,
            Math.min(
                100,
                Math.floor(
                    bodyHeight
                    / rowHeight,
                ),
            ),
        )

    const totalRows =
        table
            .getCoreRowModel()
            .rows.length

    const finalSize =
        totalRows > 0
            ? Math.min(
                  desired,
                  totalRows,
              )
            : desired

    if (
        pageSize.value
        !== finalSize
    ) {
        pageSize.value =
            finalSize

        pageIndex.value = 0
    }
}

function queueAutoPageSize() {
    if (autoSizeRaf) {
        cancelAnimationFrame(
            autoSizeRaf,
        )
    }

    autoSizeRaf =
        requestAnimationFrame(
            async () => {
                await nextTick()

                calculateAutoPageSize()
            },
        )
}

function applyPageSizeSelection() {
    pageIndex.value = 0

    if (
        pageSizeSelection.value
        === 'auto'
    ) {
        queueAutoPageSize()
        return
    }

    pageSize.value =
        Number(
            pageSizeSelection.value,
        )
}

onMounted(async () => {
    await nextTick()

    queueAutoPageSize()

    if (
        rootRef.value
        && typeof ResizeObserver
            !== 'undefined'
    ) {
        resizeObserver =
            new ResizeObserver(
                () => {
                    queueAutoPageSize()
                },
            )

        resizeObserver.observe(
            rootRef.value,
        )
    }
})

onBeforeUnmount(() => {
    resizeObserver?.disconnect()

    if (autoSizeRaf) {
        cancelAnimationFrame(
            autoSizeRaf,
        )
    }
})

watch(
    () => props.data.length,
    () => {
        queueAutoPageSize()
    },
)

watch(
    () => props.fillHeight,
    () => {
        queueAutoPageSize()
    },
)
</script>

<template>
    <div
        ref="rootRef"
        v-fill-viewport-table="{
            enabled: props.fillHeight,
            bottomGap: 16,
            minHeight: 320,
            mode: 'fill',
        }"
        class="rounded-lg border border-slate-700 bg-slate-900"
        :class="
            props.fillHeight
                ? 'flex min-h-0 flex-col overflow-hidden'
                : ''
        "
    >
        <!-- TOP CONTROLS -->
        <div
            data-dt-controls
            class="flex shrink-0 flex-col gap-3 border-b border-slate-800 px-4 py-3 text-sm text-slate-300 md:flex-row md:items-center md:justify-between"
        >
            <div
                class="flex flex-wrap items-center gap-3"
            >
                <div
                    class="flex items-center gap-2"
                >
                    <span>Show</span>

                    <select
                        v-model="pageSizeSelection"
                        class="h-9 rounded-md border border-slate-700 bg-black px-3 text-sm text-white outline-none transition focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        @change="
                            applyPageSizeSelection
                        "
                    >
                        <option
                            v-if="
                                props.autoPageSize
                            "
                            value="auto"
                        >
                            Auto
                        </option>

                        <option :value="5">
                            5
                        </option>

                        <option :value="10">
                            10
                        </option>

                        <option :value="20">
                            20
                        </option>

                        <option :value="50">
                            50
                        </option>

                        <option :value="100">
                            100
                        </option>
                    </select>

                    <span>entries</span>
                </div>

            </div>

            <div
                class="text-xs font-medium text-slate-300"
            >
                Page
                {{
                    table.getState()
                        .pagination
                        .pageIndex + 1
                }}
                of
                {{
                    table.getPageCount()
                    || 1
                }}
            </div>
        </div>

        <!-- TABLE SCROLL AREA -->
        <div
            class="relative w-full overflow-x-auto"
            :class="
                props.fillHeight
                    ? 'min-h-0 flex-1 overflow-y-auto'
                    : ''
            "
        >
            <Table
                class="w-full table-fixed border-collapse text-sm"
            >
                <TableHeader
                    class="sticky top-0 z-20 bg-slate-800"
                >
                    <TableRow
                        v-for="
                            headerGroup
                            in table
                                .getHeaderGroups()
                        "
                        :key="
                            headerGroup.id
                        "
                    >
                        <TableHead
                            v-for="
                                header
                                in headerGroup.headers
                            "
                            :key="
                                header.id
                            "
                            class="truncate border-b border-slate-600 px-4 py-3 text-left font-semibold text-slate-200"
                            :style="{
                                width:
                                    header.column.id
                                    === 'actions'
                                        ? '220px'
                                        : 'auto',
                            }"
                        >
                            <FlexRender
                                v-if="
                                    !header.isPlaceholder
                                "
                                :render="
                                    header.column
                                        .columnDef
                                        .header
                                "
                                :props="
                                    header
                                        .getContext()
                                "
                            />
                        </TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    <TableRow
                        v-for="
                            row
                            in table
                                .getPaginationRowModel()
                                .rows
                        "
                        :key="row.id"
                        class="border-b border-slate-800 hover:bg-slate-800/60"
                    >
                        <TableCell
                            v-for="
                                cell
                                in row
                                    .getVisibleCells()
                            "
                            :key="cell.id"
                            class="truncate px-4 py-3 text-slate-100"
                            :style="{
                                width:
                                    cell.column.id
                                    === 'actions'
                                        ? '220px'
                                        : 'auto',
                            }"
                        >
                            <FlexRender
                                :render="
                                    cell.column
                                        .columnDef
                                        .cell
                                "
                                :props="
                                    cell
                                        .getContext()
                                "
                            />
                        </TableCell>
                    </TableRow>

                    <TableRow
                        v-if="
                            !table
                                .getPaginationRowModel()
                                .rows.length
                        "
                    >
                        <TableCell
                            :colspan="
                                table
                                    .getAllColumns()
                                    .length
                            "
                            class="px-4 py-6 text-center text-slate-400"
                        >
                            No results found
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- PAGINATION -->
        <div
            data-dt-pagination
            class="flex shrink-0 justify-end gap-2 border-t border-slate-800 bg-slate-900 px-4 py-3"
        >
            <button
                type="button"
                class="rounded-md border border-slate-700 bg-slate-950 px-3 py-1.5 text-sm text-slate-200 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="
                    !table
                        .getCanPreviousPage()
                "
                @click="
                    table.previousPage()
                "
            >
                Previous
            </button>

            <button
                type="button"
                class="rounded-md border border-slate-700 bg-slate-950 px-3 py-1.5 text-sm text-slate-200 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="
                    !table
                        .getCanNextPage()
                "
                @click="
                    table.nextPage()
                "
            >
                Next
            </button>
        </div>
    </div>
</template>
