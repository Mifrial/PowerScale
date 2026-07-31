import type { RouteLocationNormalizedLoaded } from 'vue-router'
import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    layout?: string
    crumb?: BreadcrumbResolver
  }
}

export interface BreadcrumbItem {
  title: string
  to?: string
}

export type BreadcrumbResolver = (to: RouteLocationNormalizedLoaded) => BreadcrumbItem[]
