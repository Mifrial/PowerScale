import type { RouteRecordRaw } from 'vue-router'
import { routes as dashboardRoutes } from '@/modules/Dashboard/routes'
import { routes as chatRoutes } from '@/modules/Messages/Chat/routes'
import {
  routes as notificationRoutes,
  adminChildren as notificationAdminChildren,
} from '@/modules/Messages/Notifications/routes'
import {
  routes as userRoutes,
  adminChildren as userAdminChildren,
} from '@/modules/Core/User/routes'
import {
  roleplayRoutes,
  roleplayAdminChildren,
} from '@/modules/Roleplay/routes'

const adminRoutes: RouteRecordRaw = {
  path: 'admin',
  meta: { crumb: () => [{ title: 'Администрирование', to: '/admin' }] },
  children: [
    ...userAdminChildren,
    ...roleplayAdminChildren,
    ...notificationAdminChildren,
  ],
}

export const moduleChildren: RouteRecordRaw[] = [
  ...dashboardRoutes,
  ...chatRoutes,
  ...notificationRoutes,
  ...userRoutes,
  ...roleplayRoutes,
  adminRoutes,
]

function assertUniqueRoutes(records: RouteRecordRaw[], level: string): void {
  const paths = new Set<string>()
  const names = new Set<string>()
  for (const record of records) {
    if (record.name) {
      const name = String(record.name)
      if (names.has(name)) console.warn(`[router] duplicate name "${name}" at ${level}`)
      names.add(name)
    }
    if (paths.has(record.path)) console.warn(`[router] duplicate path "${record.path}" at ${level}`)
    paths.add(record.path)
    if (record.children) assertUniqueRoutes(record.children, `${level}/${record.path}`)
  }
}

if (import.meta.env.DEV) {
  assertUniqueRoutes(moduleChildren, '/')
}
