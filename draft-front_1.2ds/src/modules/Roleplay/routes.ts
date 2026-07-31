import type { RouteRecordRaw } from 'vue-router'
import { routes as characterRoutes } from './Character/routes'
import { routes as gameRoutes } from './Game/routes'
import { createSpaceRoutes } from './Space/routes'
import { ruleCtxChildren } from './Rule/routes'
import { adminChildren as tagAdminChildren } from './Rule/Tag/routes'

export const roleplayRoutes: RouteRecordRaw[] = [
  ...characterRoutes,
  ...gameRoutes,
  ...createSpaceRoutes(ruleCtxChildren),
]

export const roleplayAdminChildren: RouteRecordRaw[] = tagAdminChildren
