import type { RouteRecordRaw } from 'vue-router'

export const routes: RouteRecordRaw[] = [
  {
    path: '',
    name: 'Dashboard',
    component: () => import('./Page/DashboardPage.vue'),
    meta: { title: 'Главная' },
  },
]
