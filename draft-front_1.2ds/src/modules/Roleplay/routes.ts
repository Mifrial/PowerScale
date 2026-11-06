import type { RouteRecordRaw } from 'vue-router';
import { routes as characterRoutes } from '@/modules/Roleplay/Character/routes';
import { routes as gameRoutes } from '@/modules/Roleplay/Game/routes';
import { routes as mapRoutes } from '@/modules/Roleplay/Map/routes';
import { createRuleSpaceRoutes } from '@/modules/Roleplay/RuleSpace/routes';
import { adminChildren as keywordAdminChildren } from '@/modules/Roleplay/Keyword/routes';
import { adminChildren as mechanicAdminChildren } from '@/modules/Roleplay/Mechanic/routes';
import { ruleCtxChildren } from '@/modules/Roleplay/Rule/routes';

export const roleplayRoutes: RouteRecordRaw[] = [
  ...characterRoutes,
  ...gameRoutes,
  ...mapRoutes,
  ...createRuleSpaceRoutes(ruleCtxChildren),
];

export const roleplayAdminChildren: RouteRecordRaw[] = [...keywordAdminChildren, ...mechanicAdminChildren];
