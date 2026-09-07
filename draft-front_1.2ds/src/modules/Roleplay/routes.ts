import type { RouteRecordRaw } from 'vue-router';
import { routes as characterRoutes } from '@/modules/Roleplay/Character/routes';
import { routes as gameRoutes } from '@/modules/Roleplay/Game/routes';
import { createRuleSpaceRoutes } from '@/modules/Roleplay/RuleSpace/routes';
import { adminChildren as keywordAdminChildren } from '@/modules/Roleplay/Keyword/routes';
import { adminChildren as ruleAdminChildren, ruleCtxChildren } from '@/modules/Roleplay/Rule/routes';

export const roleplayRoutes: RouteRecordRaw[] = [
  ...characterRoutes,
  ...gameRoutes,
  ...createRuleSpaceRoutes(ruleCtxChildren),
];

export const roleplayAdminChildren: RouteRecordRaw[] = [...keywordAdminChildren, ...ruleAdminChildren];
