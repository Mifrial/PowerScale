import type { RouteRecordRaw } from 'vue-router'

export const routes: RouteRecordRaw[] = [
  {
    path: '',
    name: 'Home',
    component: () => import('./Page/DashboardPage.vue'),
    meta: { title: 'Главная', guestAllowed: true },
  },
]
