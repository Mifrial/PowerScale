import type { RouteRecordRaw } from 'vue-router'
import { routes as characterRoutes } from '@/modules/Roleplay/Character/routes'
import { routes as gameRoutes } from '@/modules/Roleplay/Game/routes'
import { createSpaceRoutes } from '@/modules/Roleplay/Space/routes'
import { ruleCtxChildren } from '@/modules/Roleplay/Rule/routes'
import { adminChildren as tagAdminChildren } from '@/modules/Roleplay/Rule/routes'

export const roleplayRoutes: RouteRecordRaw[] = [
  ...characterRoutes,
  ...gameRoutes,
  ...createSpaceRoutes(ruleCtxChildren),
]

export const roleplayAdminChildren: RouteRecordRaw[] = tagAdminChildren
