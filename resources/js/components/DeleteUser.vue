<script setup lang="ts">
import { ref } from 'vue'
import {
  useVueTable,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  FlexRender,
} from '@tanstack/vue-table'
import {
  Table,
  TableHeader,
  TableRow,
  TableHead,
  TableBody,
  TableCell,
} from '@/components/ui/table'

const props = defineProps<{ data: any[]; columns: any[] }>()

const globalFilter = ref('')

// pagination state
const pageSize = ref(10)
const pageIndex = ref(0)

const table = useVueTable({
  get data() {
    return props.data
  },
  get columns() {
    return props.columns
  },
  state: {
    get globalFilter() {
      return globalFilter.value
    },
    get pagination() {
      return {
        pageIndex: pageIndex.value,
        pageSize: pageSize.value,
      }
    },
  },
  onPaginationChange: updater => {
    const value =
      typeof updater === 'function'
        ? updater({
            pageIndex: pageIndex.value,
            pageSize: pageSize.value,
          })
        : updater

    pageIndex.value = value.pageIndex
    pageSize.value = value.pageSize
  },
  onGlobalFilterChange: value => {
    globalFilter.value = String(value)
    pageIndex.value = 0
  },
  getCoreRowModel: getCoreRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
})
</script>
<template>
  <div class="rounded-lg border border-slate-700 bg-slate-900">

    <!-- RESPONSIVE WRAPPER -->
    <div class="relative w-full overflow-x-auto">

      <Table class="w-full table-fixed border-collapse text-sm">

        <!-- HEADER -->
        <TableHeader class="bg-slate-800">
          <TableRow
            v-for="headerGroup in table.getHeaderGroups()"
            :key="headerGroup.id"
          >
            <TableHead
              v-for="header in headerGroup.headers"
              :key="header.id"
              class="truncate px-4 py-3 text-left font-semibold text-slate-200"
              :style="{
                width: header.column.id === 'actions'
                  ? '220px'
                  : 'auto'
              }"
            >
              <FlexRender
                :render="header.column.columnDef.header"
                :props="header.getContext()"
              />
            </TableHead>
          </TableRow>
        </TableHeader>

        <!-- BODY -->
        <TableBody>
          <TableRow
            v-for="row in table.getPaginationRowModel().rows"
            :key="row.id"
            class="border-b border-slate-800 hover:bg-slate-800/60"
          >
            <TableCell
              v-for="cell in row.getVisibleCells()"
              :key="cell.id"
              class="truncate px-4 py-3 text-slate-100"
              :style="{
                width: cell.column.id === 'actions'
                  ? '220px'
                  : 'auto'
              }"
            >
              <FlexRender
                :render="cell.column.columnDef.cell"
                :props="cell.getContext()"
              />
            </TableCell>
          </TableRow>

          <!-- EMPTY STATE -->
          <TableRow v-if="!table.getPaginationRowModel().rows.length">
            <TableCell
              :colspan="table.getAllColumns().length"
              class="px-4 py-6 text-center text-slate-400"
            >
              No results found
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <!-- PAGINATION FOOTER -->
    <div class="flex items-center justify-between px-4 py-3 text-sm text-slate-300">

      <!-- Entries per page -->
      <div class="flex items-center gap-2">
        <select
          v-model.number="pageSize"
          @change="pageIndex = 0"
          class="rounded-md border border-slate-700 bg-slate-800 px-2 py-1 text-slate-200 focus:outline-none"
        >
          <option :value="10">10</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
          <option :value="100">100</option>
        </select>
        <span>entries per page</span>
      </div>

      <!-- Page info -->
      <div>
        Page {{ table.getState().pagination.pageIndex + 1 }}
        of {{ table.getPageCount() }}
      </div>
    </div>

  </div>
</template>
