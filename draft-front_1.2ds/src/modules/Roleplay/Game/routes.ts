import type { RouteRecordRaw } from 'vue-router'

export const routes: RouteRecordRaw[] = [
  {
    path: 'games',
    name: 'Games',
    component: () => import('@/modules/Roleplay/Game/Page/GamesPage.vue'),
    meta: { title: 'Игры', guestAllowed: true },
  },
]
