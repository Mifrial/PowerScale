import type { RouteLocationRaw } from 'vue-router';

export type RouteAccessDecision = { allow: true } | { allow: false; redirect: RouteLocationRaw };
