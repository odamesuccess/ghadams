<script setup lang="ts" generic="TData">
import {
    FlexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    useVueTable,
    type ColumnDef,
} from '@tanstack/vue-table';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<{
        /*
         * STANDARD MODE
         * -------------
         * Existing TanStack-backed DataTable pages continue to
         * pass data + columns exactly as before.
         */
        data?: TData[];
        preservePageOnDataChange?: boolean;
        columns?: ColumnDef<TData>[];
        searchable?: boolean;
        fillHeight?: boolean;
        autoPageSize?: boolean;
        paginate?: boolean;
        showControls?: boolean;
        emptyText?: string;

        /*
         * LEGACY MIGRATION MODE
         * ---------------------
         * Page-level custom/native tables use:
         *
         *     <DataTable legacy> ... </DataTable>
         *
         * The shared component becomes the ONLY place that owns
         * the actual semantic HTML <table> element.
         *
         * Existing page thead/tbody/tr/td markup, Vue bindings,
         * server-side pagination, custom buttons and print CSS are
         * preserved through the default slot.
         */
        legacy?: boolean;
    }>(),
    {
        preservePageOnDataChange: false,
        searchable: true,
        fillHeight: true,
        autoPageSize: true,
        paginate: true,
        showControls: true,
        emptyText: 'No results found',
        legacy: false,
    },
);

const globalFilter = ref('');
const pageSize = ref(10);
const pageIndex = ref(0);

const pageSizeSelection =
    ref<'auto' | number>(
        props.autoPageSize
            ? 'auto'
            : 10,
    );

const rootRef =
    ref<HTMLElement | null>(null);

/*
 * Stable screen capacity calculated from the real remaining viewport.
 * This prevents small datasets from being trapped at a low row count.
 */
const autoCapacity =
    ref(10);

let resizeObserver:
    | ResizeObserver
    | null = null;

let autoSizeRaf:
    | number
    | null = null;

let convergenceTimer:
    | ReturnType<typeof setTimeout>
    | null = null;

let lastAutoSignature = '';

const table = useVueTable({
    get autoResetPageIndex() { return !props.preservePageOnDataChange; },
    get data() {
        return props.data ?? [];
    },

    get columns() {
        return props.columns ?? [];
    },

    state: {
        get globalFilter() {
            return globalFilter.value;
        },

        get pagination() {
            return {
                pageIndex:
                    pageIndex.value,
                pageSize:
                    pageSize.value,
            };
        },
    },

    onGlobalFilterChange: (
        value,
    ) => {
        globalFilter.value =
            String(value);

        pageIndex.value = 0;

        queueAutoPageSize();
    },

    onPaginationChange: (
        updater,
    ) => {
        const next =
            typeof updater
                === 'function'
                ? updater({
                      pageIndex:
                          pageIndex.value,
                      pageSize:
                          pageSize.value,
                  })
                : updater;

        pageIndex.value =
            next.pageIndex;

        pageSize.value =
            next.pageSize;
    },

    getCoreRowModel:
        getCoreRowModel(),

    getFilteredRowModel:
        getFilteredRowModel(),

    getPaginationRowModel:
        getPaginationRowModel(),
});

const filteredRowCount =
    computed(
        () =>
            table
                .getFilteredRowModel()
                .rows.length,
    );

const visibleRows =
    computed(() =>
        props.paginate
            ? table
                  .getPaginationRowModel()
                  .rows
            : table
                  .getFilteredRowModel()
                  .rows,
    );

const firstVisibleRow =
    computed(() => {
        if (
            filteredRowCount.value
            === 0
        ) {
            return 0;
        }

        if (!props.paginate) {
            return 1;
        }

        return (
            pageIndex.value
            * pageSize.value
            + 1
        );
    });

const lastVisibleRow =
    computed(() =>
        props.paginate
            ? Math.min(
                  (
                      pageIndex.value
                      + 1
                  )
                      * pageSize.value,
                  filteredRowCount.value,
              )
            : filteredRowCount.value,
    );

const shouldFillHeight =
    computed(() => {
        if (
            props.legacy
            || !props.paginate
            || !props.fillHeight
        ) {
            return false;
        }

        if (
            pageSizeSelection.value
            === 'auto'
        ) {
            return (
                filteredRowCount.value
                > autoCapacity.value
            );
        }

        return (
            filteredRowCount.value
            > Number(
                pageSizeSelection.value,
            )
        );
    });

function median(
    values: number[],
): number {
    if (!values.length) {
        return 34;
    }

    const sorted =
        [...values].sort(
            (a, b) => a - b,
        );

    const middle =
        Math.floor(
            sorted.length / 2,
        );

    if (
        sorted.length % 2
        === 0
    ) {
        return (
            sorted[middle - 1]
            + sorted[middle]
        ) / 2;
    }

    return sorted[middle];
}

function measureRenderedRowHeight(
    root: HTMLElement,
): number {
    const rows =
        Array.from(
            root.querySelectorAll(
                'tbody tr',
            ),
        )
            .slice(0, 8)
            .map(
                (row) =>
                    (
                        row as HTMLElement
                    )
                        .getBoundingClientRect()
                        .height,
            )
            .filter(
                (height) =>
                    height >= 20,
            );

    /*
     * Use the real rendered LMS row density. The previous
     * 36px minimum caused compact pages such as Most Active
     * Students to show too few rows and leave a large blank
     * area before pagination.
     */
    return Math.max(
        24,
        median(rows),
    );
}

async function calculateAutoPageSize() {
    if (
        props.legacy
        || !props.paginate
        || !props.fillHeight
        || !props.autoPageSize
        || pageSizeSelection.value
            !== 'auto'
        || !rootRef.value
    ) {
        return;
    }

    await nextTick();

    const root =
        rootRef.value;

    const controls =
        root.querySelector(
            '[data-dt-controls]',
        ) as HTMLElement | null;

    const pagination =
        root.querySelector(
            '[data-dt-pagination]',
        ) as HTMLElement | null;

    const thead =
        root.querySelector(
            'thead',
        ) as HTMLElement | null;

    const controlsHeight =
        controls
            ?.getBoundingClientRect()
            .height
        ?? 0;

    const paginationHeight =
        pagination
            ?.getBoundingClientRect()
            .height
        ?? 0;

    const headerHeight =
        thead
            ?.getBoundingClientRect()
            .height
        ?? 38;

    const rowHeight =
        measureRenderedRowHeight(
            root,
        );

    /*
     * Calculate capacity from the REAL viewport and the DataTable's
     * actual top position. Do not derive capacity from the current
     * compact table height.
     */
    const viewportHeight =
        window.visualViewport?.height
        ?? window.innerHeight;

    const rootTop =
        root
            .getBoundingClientRect()
            .top;

    const remainingHeight =
        viewportHeight
        - Math.max(
            rootTop,
            0,
        )
        - 10;

    const availableBodyHeight =
        Math.max(
            rowHeight,
            remainingHeight
            - controlsHeight
            - paginationHeight
            - headerHeight
            - 2,
        );

    const capacity =
        Math.max(
            5,
            Math.min(
                100,
                Math.floor(
                    availableBodyHeight
                    / rowHeight,
                ),
            ),
        );

    autoCapacity.value =
        capacity;

    const totalFiltered =
        table
            .getFilteredRowModel()
            .rows.length;

    /*
     * If every record fits on the current device, show every record.
     * Otherwise use the exact number of rows that fit.
     */
    const finalSize =
        totalFiltered > 0
            ? Math.min(
                  capacity,
                  totalFiltered,
              )
            : capacity;

    if (
        pageSize.value
        !== finalSize
    ) {
        pageSize.value =
            finalSize;

        pageIndex.value = props.preservePageOnDataChange
            ? Math.min(pageIndex.value, Math.max(0, Math.ceil(totalFiltered / Math.max(1, finalSize)) - 1))
            : 0;
    }

    /*
     * Recheck once when the measured layout changes. This handles
     * late image/avatar/font layout without creating a timer loop.
     */
    const signature = [
        Math.round(rootTop),
        Math.round(viewportHeight),
        Math.round(rowHeight),
        Math.round(controlsHeight),
        Math.round(headerHeight),
        Math.round(paginationHeight),
        capacity,
        totalFiltered,
    ].join(':');

    if (
        signature
        !== lastAutoSignature
    ) {
        lastAutoSignature =
            signature;

        if (convergenceTimer) {
            clearTimeout(
                convergenceTimer,
            );
        }

        convergenceTimer =
            setTimeout(
                () => {
                    if (
                        pageSizeSelection.value
                        === 'auto'
                    ) {
                        queueAutoPageSize();
                    }
                },
                120,
            );
    }
}

function queueAutoPageSize() {
    if (autoSizeRaf) {
        cancelAnimationFrame(
            autoSizeRaf,
        );
    }

    autoSizeRaf =
        requestAnimationFrame(
            () => {
                void calculateAutoPageSize();
            },
        );
}

function applyPageSizeSelection() {
    pageIndex.value = 0;

    if (
        pageSizeSelection.value
        === 'auto'
    ) {
        lastAutoSignature = '';
        queueAutoPageSize();
        return;
    }

    pageSize.value =
        Number(
            pageSizeSelection.value,
        );
}

onMounted(async () => {
    await nextTick();

    queueAutoPageSize();

    if (
        rootRef.value
        && typeof ResizeObserver
            !== 'undefined'
    ) {
        resizeObserver =
            new ResizeObserver(
                () => {
                    queueAutoPageSize();
                },
            );

        resizeObserver.observe(
            rootRef.value,
        );
    }

    window.visualViewport
        ?.addEventListener(
            'resize',
            queueAutoPageSize,
        );
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();

    window.visualViewport
        ?.removeEventListener(
            'resize',
            queueAutoPageSize,
        );

    if (autoSizeRaf) {
        cancelAnimationFrame(
            autoSizeRaf,
        );
    }

    if (convergenceTimer) {
        clearTimeout(
            convergenceTimer,
        );
    }
});

watch(
    () => props.data?.length ?? 0,
    () => {
        if (props.preservePageOnDataChange) {
            const count = table.getFilteredRowModel().rows.length;
            pageIndex.value = Math.min(pageIndex.value, Math.max(0, Math.ceil(count / Math.max(1, pageSize.value)) - 1));
        }
        queueAutoPageSize();
    },
);

watch(
    () => props.fillHeight,
    () => {
        queueAutoPageSize();
    },
);

watch(
    () => filteredRowCount.value,
    () => {
        queueAutoPageSize();
    },
);
</script>

<template>
    <!--
        UNIVERSAL SHARED DATATABLE — LEGACY MODE
        -----------------------------------------
        All former page-level native <table> tags are migrated
        to <DataTable legacy>. This shared component renders the
        one semantic table element and forwards ALL original table
        attributes through $attrs.

        Because the component root in legacy mode is itself the
        semantic table, existing wrappers, scoped CSS, print layouts,
        Vue conditions, events, classes and server-side pagination
        remain structurally compatible.
    -->
    <table
        v-if="props.legacy"
        v-bind="$attrs"
    >
        <slot />
    </table>

    <div
        v-else
        ref="rootRef"
        v-fill-viewport-table="{
            enabled:
                props.fillHeight,
            bottomGap: 10,
            minHeight: 280,
            mode:
                shouldFillHeight
                    ? 'fill'
                    : 'max',
        }"
        class="overflow-hidden rounded-xl border border-slate-800 bg-slate-950 shadow-sm"
        :class="
            shouldFillHeight
                ? 'flex min-h-0 flex-col'
                : ''
        "
    >
        <!-- TOP CONTROLS -->
        <div
            v-if="props.showControls && props.paginate"
            data-dt-controls
            class="shrink-0 overflow-x-auto border-b border-slate-800 bg-slate-950"
        >
            <div
                class="flex min-w-max items-center justify-between gap-4 px-3 py-2 text-xs text-slate-400 sm:text-sm"
            >
                <div
                    class="flex items-center gap-3 whitespace-nowrap"
                >
                    <div
                        class="flex items-center gap-2"
                    >
                        <span>Show</span>

                        <select
                            v-model="
                                pageSizeSelection
                            "
                            class="h-8 rounded-md border border-slate-700 bg-slate-900 px-2 text-slate-200 outline-none focus:border-cyan-400"
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

                            <option
                                :value="5"
                            >
                                5
                            </option>

                            <option
                                :value="10"
                            >
                                10
                            </option>

                            <option
                                :value="20"
                            >
                                20
                            </option>

                            <option
                                :value="50"
                            >
                                50
                            </option>

                            <option
                                :value="100"
                            >
                                100
                            </option>
                        </select>

                        <span>entries</span>
                    </div>

                </div>

                <div
                    class="whitespace-nowrap"
                >
                    Page
                    <strong
                        class="text-white"
                    >
                        {{
                            table
                                .getState()
                                .pagination
                                .pageIndex
                            + 1
                        }}
                    </strong>
                    of
                    <strong
                        class="text-white"
                    >
                        {{
                            Math.max(
                                table
                                    .getPageCount(),
                                1,
                            )
                        }}
                    </strong>
                </div>
            </div>
        </div>

        <!-- TABLE BODY -->
        <div
            class="relative w-full overflow-x-auto"
            :class="
                shouldFillHeight
                    ? 'min-h-0 flex-1 overflow-y-auto'
                    : ''
            "
        >
            <Table
                class="min-w-max border-collapse text-xs sm:text-sm"
            >
                <TableHeader
                    class="sticky top-0 z-20 bg-slate-800/95"
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
                            class="border-b border-slate-700 px-3 py-2.5 text-left text-xs font-bold tracking-wide whitespace-nowrap text-slate-200 uppercase"
                        >
                            <FlexRender
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
                            in visibleRows
                        "
                        :key="row.id"
                        class="border-b border-slate-800 transition hover:bg-slate-800/55"
                    >
                        <TableCell
                            v-for="
                                cell
                                in row
                                    .getVisibleCells()
                            "
                            :key="cell.id"
                            class="px-3 py-2.5 align-middle whitespace-nowrap text-slate-100"
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
                            !visibleRows.length
                        "
                    >
                        <TableCell
                            :colspan="
                                table
                                    .getAllColumns()
                                    .length
                            "
                            class="px-4 py-10 text-center font-semibold text-slate-400"
                        >
                            {{ props.emptyText }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- FOOTER -->
        <div
            v-if="props.showControls && props.paginate"
            data-dt-pagination
            class="flex shrink-0 flex-col gap-3 border-t border-slate-800 bg-slate-950 px-3 py-2 sm:flex-row sm:items-center sm:justify-between"
        >
            <div
                class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400 sm:text-sm"
            >
                <span>
                    Showing
                    {{ firstVisibleRow }}
                    to
                    {{ lastVisibleRow }}
                    of
                    {{ filteredRowCount }}
                    entr<span
                        v-if="
                            filteredRowCount
                            === 1
                        "
                        >y</span
                    ><span v-else>ies</span>
                </span>

                <slot
                    name="footer-summary"
                    :start="
                        firstVisibleRow
                    "
                    :end="
                        lastVisibleRow
                    "
                    :total="
                        filteredRowCount
                    "
                />
            </div>

            <div
                class="flex items-center justify-end gap-2"
            >
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-700 px-2.5 text-xs font-semibold text-slate-200 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="
                        !table
                            .getCanPreviousPage()
                    "
                    @click="
                        table.previousPage()
                    "
                >
                    <ChevronLeft
                        class="h-3.5 w-3.5"
                    />
                    Previous
                </button>

                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-700 px-2.5 text-xs font-semibold text-slate-200 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="
                        !table
                            .getCanNextPage()
                    "
                    @click="
                        table.nextPage()
                    "
                >
                    Next
                    <ChevronRight
                        class="h-3.5 w-3.5"
                    />
                </button>
            </div>
        </div>
    </div>
</template>
