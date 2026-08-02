import type { RouteLocationNormalizedLoaded } from 'vue-router'
import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    layout?: string
    crumb?: BreadcrumbResolver
    /** Страница доступна гостю (обходит все requires*). Игроки/админы проходят без ограничений. */
    guestAllowed?: boolean
    /** Достаточно хотя бы одного ключа (OR). Мержится с родительским meta. */
    requiresAny?: string[]
    /** Нужны все ключи (AND). Мержится с родительским meta. */
    requiresAll?: string[]
    /** Страница раздела «Администрирование»: доступ через реестр админ-секций (isAdmin). */
    admin?: boolean
  }
}

export interface BreadcrumbItem {
  title: string
  to?: string
}

export type BreadcrumbResolver = (to: RouteLocationNormalizedLoaded) => BreadcrumbItem[]
