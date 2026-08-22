import type { RouteLocationNormalizedLoaded } from 'vue-router';
import type { BreadcrumbItem } from '@/router/BreadcrumbItem';

export type BreadcrumbResolver = (to: RouteLocationNormalizedLoaded) => BreadcrumbItem[];
