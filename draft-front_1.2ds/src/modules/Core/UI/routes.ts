import type { RouteRecordRaw } from 'vue-router'

export const routes: RouteRecordRaw[] = [
  {
    path: '/404',
    name: 'NotFound',
    component: () => import('./Page/NotFoundPage.vue'),
    meta: { title: 'Страница не найдена', guestAllowed: true },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFoundCatchAll',
    redirect: '/404',
    meta: { guestAllowed: true },
  },
]
