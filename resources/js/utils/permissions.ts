import { usePage } from '@inertiajs/vue3'
import type { PageProps as InertiaPageProps } from '@inertiajs/core'

interface PageProps extends InertiaPageProps {
  auth: InertiaPageProps['auth'] & {
    permissions: string[]
  }
}

const permissionAliases = (permission: string): string[] => {
  const parts = permission.split('.')

  if (parts.length !== 2) return [permission]

  const [first, second] = parts

  return [
    permission,
    `${second}.${first}`,
  ]
}

export function can(permission: string): boolean {
  const page = usePage<PageProps>()
  const permissions = page.props.auth?.permissions ?? []

  return permissionAliases(permission).some(alias => permissions.includes(alias))
}
