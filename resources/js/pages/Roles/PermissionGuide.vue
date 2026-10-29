<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'
interface Page { route: string; label: string; url: string; permissions: string[]; permission_groups: string[][] }
interface Role { id: number; name: string; is_special: boolean; landing_route: string | null; permissions: string[] }
const props = defineProps<{ pages: Page[]; permissions: { id: number; name: string; guard_name: string }[]; roles: Role[] }>()
const search = ref('')
const roleId = ref('')
const tab = ref('pages')
const eligibleOnly = ref(false)
watch(roleId, (value) => {
  eligibleOnly.value = value !== ''
  tab.value = 'pages'
  search.value = ''
})
const role = computed(() => props.roles.find(r => String(r.id) === roleId.value))
const missing = (page: Page) => page.permission_groups.filter(group => !group.some(p => role.value?.permissions.includes(p)))
const eligible = (page: Page) => !!role.value && missing(page).length === 0
const matches = (text: string) => text.toLowerCase().includes(search.value.trim().toLowerCase())
const pages = computed(() => props.pages.filter(p => matches([p.label, p.route, p.url, ...p.permissions].join(' ')) && (!eligibleOnly.value || !role.value || eligible(p))))
const rolePermissions = computed(() => role.value ? props.permissions.filter(p => role.value!.permissions.includes(p.name)) : props.permissions)
const permissions = computed(() => rolePermissions.value.filter(p => matches(p.name)))
const linkedPages = (permission: string) => props.pages.filter(p => p.permissions.includes(permission))
const eligibleCount = computed(() => role.value ? props.pages.filter(eligible).length : 0)
const chosenPage = computed(() => props.pages.find(p => p.route === role.value?.landing_route))
const chosenValid = computed(() => !!chosenPage.value && eligible(chosenPage.value))
const effectivePage = computed(() => !role.value?.is_special ? null : chosenValid.value ? chosenPage.value : props.pages.find(eligible))
</script>
<template>
  <Head title="Permissions and Landing Pages Guide" />
  <AppLayout :breadcrumbs="[{ title: 'Permission Guide', href: '/role-permission-guide' }]">
    <div class="space-y-5 p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-semibold">Permissions and landing pages</h1><p class="text-sm text-muted-foreground">Live guide from registered routes and current role permissions. Reload to see recent changes.</p></div>
        <Link href="/roles/index" class="rounded border px-4 py-2">Back to roles</Link>
      </div>
      <div class="rounded-lg border bg-muted/30 p-4 text-sm space-y-2">
        <p>A special role needs a page permission such as <code>assets.view</code> to land on Assets Index. A Menu permission controls sidebar visibility; it does not grant page access.</p>
        <p><strong>OR</strong> means any permission in that group works. <strong>AND</strong> means every group must be satisfied. Dashboard pages, downloads and routes requiring an item ID are excluded from landing choices.</p>
        <p>This is a guide. Change assignments and the chosen landing page in the role editor.</p>
      </div>
      <div class="flex flex-wrap gap-3">
        <input v-model="search" aria-label="Search pages or permissions" placeholder="Search page, route or permission…" class="min-w-64 flex-1 rounded border bg-background px-3 py-2" />
        <select v-model="roleId" aria-label="Preview role permissions" class="rounded border bg-background px-3 py-2"><option value="">All pages — select a role to check access</option><option v-for="r in roles" :key="r.id" :value="String(r.id)">{{ r.name }}{{ r.is_special ? ' (Special)' : '' }}</option></select>
      </div>
      <div v-if="role" class="rounded-lg border p-4 text-sm space-y-2">
        <p><strong>{{ role.name }}</strong>: {{ role.permissions.length }} assigned permissions; {{ eligibleCount }} permission-backed landing pages.</p>
        <p v-if="!role.is_special">Normal role. These are potential permission-backed pages if you make this role special; its normal dashboard behavior is unchanged.</p>
        <p v-else-if="role.landing_route">Chosen landing: <code>{{ role.landing_route }}</code> — {{ chosenValid ? 'eligible' : 'no longer eligible; another assigned page is used if available' }}.</p>
        <p v-else-if="eligibleCount">No chosen landing page. A special role uses its first eligible page until you select one.</p>
        <p v-else>No eligible landing page. Assign the required permissions in the role editor.</p>
        <p v-if="effectivePage">Current special-role destination: <strong>{{ effectivePage.label }}</strong> <code>{{ effectivePage.url }}</code>.</p>
        <label class="flex items-center gap-2"><input v-model="eligibleOnly" type="checkbox" /> Show only this role’s permitted landing pages (untick to inspect missing permissions)</label>
      </div>
      <div class="flex gap-3"><button class="rounded border px-4 py-2" :class="tab === 'pages' ? 'bg-primary text-primary-foreground' : ''" @click="tab = 'pages'">Landing pages ({{ pages.length }})</button><button class="rounded border px-4 py-2" :class="tab === 'permissions' ? 'bg-primary text-primary-foreground' : ''" @click="tab = 'permissions'">{{ role ? 'Assigned permissions' : 'All permissions' }} ({{ rolePermissions.length }})</button></div>
      <p v-if="tab === 'permissions' && search.trim()" class="text-sm text-muted-foreground">Showing {{ permissions.length }} of {{ rolePermissions.length }} {{ role ? 'assigned' : 'total' }} permissions matching your search.</p>
      <div class="overflow-x-auto rounded-lg border">
        <table v-if="tab === 'pages'" class="w-full text-left text-sm">
          <thead class="bg-muted"><tr><th class="p-3">Landing page</th><th class="p-3">Required permissions</th><th v-if="role" class="p-3">Selected role</th></tr></thead>
          <tbody><tr v-for="page in pages" :key="page.route" class="border-t align-top">
            <td class="p-3"><strong>{{ page.label }}</strong><span v-if="role?.is_special && page.route === role.landing_route" class="ml-2 rounded bg-green-600 px-2 py-1 text-xs text-white">Saved landing</span><span v-else-if="effectivePage?.route === page.route" class="ml-2 rounded bg-blue-600 px-2 py-1 text-xs text-white">Automatic landing</span><div class="text-muted-foreground">{{ page.url }}</div><code class="text-xs">{{ page.route }}</code></td>
            <td class="p-3"><div v-for="(group, index) in page.permission_groups" :key="index"><span v-if="index" class="font-semibold">AND </span><code>{{ group.join(' OR ') }}</code></div></td>
            <td v-if="role" class="p-3"><strong :class="eligible(page) ? 'text-green-600' : 'text-amber-600'">{{ eligible(page) ? 'Eligible' : 'Missing permissions' }}</strong><div v-for="(group, index) in missing(page)" :key="index" class="mt-1">Add: <code>{{ group.join(' OR ') }}</code></div></td>
          </tr><tr v-if="!pages.length"><td :colspan="role ? 3 : 2" class="p-6 text-center">No permitted landing pages match. Clear the search or untick the role filter to see missing permissions.</td></tr></tbody>
        </table>
        <table v-else class="w-full text-left text-sm">
          <thead class="bg-muted"><tr><th class="p-3">Permission</th><th class="p-3">Landing pages using this permission</th><th v-if="role" class="p-3">Assigned</th></tr></thead>
          <tbody><tr v-for="permission in permissions" :key="permission.id" class="border-t align-top"><td class="p-3"><code>{{ permission.name }}</code><div class="text-xs text-muted-foreground">Guard: {{ permission.guard_name }}</div></td><td class="p-3"><div v-for="page in linkedPages(permission.name)" :key="page.route">{{ page.label }}</div><span v-if="!linkedPages(permission.name).length" class="text-muted-foreground">No eligible landing-page mapping. May control a menu or an action.</span></td><td v-if="role" class="p-3">{{ role.permissions.includes(permission.name) ? 'Yes' : 'No' }}</td></tr><tr v-if="!permissions.length"><td :colspan="role ? 3 : 2" class="p-6 text-center">No permissions match.</td></tr></tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
