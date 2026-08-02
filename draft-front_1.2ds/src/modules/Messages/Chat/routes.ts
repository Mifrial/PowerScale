import type { RouteRecordRaw } from 'vue-router'

export const routes: RouteRecordRaw[] = [
  {
    path: 'messenger',
    name: 'Messenger',
    component: () => import('./Page/MessengerPage.vue'),
    meta: { title: 'Мессенджер', guestAllowed: true },
  },
]
