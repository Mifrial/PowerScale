import type { RouteRecordRaw } from 'vue-router'

export const routes: RouteRecordRaw[] = [
  {
    path: 'characters',
    name: 'Characters',
    component: () => import('./Page/CharactersPage.vue'),
    meta: { title: 'Персонажи' },
  },
]
