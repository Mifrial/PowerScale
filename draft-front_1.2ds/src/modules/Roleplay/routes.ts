import type { RouteRecordRaw } from 'vue-router';
import { routes as characterRoutes } from '@/modules/Roleplay/Character/routes';
import { routes as gameRoutes } from '@/modules/Roleplay/Game/routes';
import { createRuleSpaceRoutes } from '@/modules/Roleplay/RuleSpace/routes';
import { ruleCtxChildren } from '@/modules/Roleplay/Rule/routes';
import { adminChildren as tagAdminChildren } from '@/modules/Roleplay/Rule/routes';

export const roleplayRoutes: RouteRecordRaw[] = [
  ...characterRoutes,
  ...gameRoutes,
  ...createRuleSpaceRoutes(ruleCtxChildren),
];

export const roleplayAdminChildren: RouteRecordRaw[] = tagAdminChildren;
