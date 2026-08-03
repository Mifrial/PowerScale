import type { RouteRecordRaw } from 'vue-router'

export const routes: RouteRecordRaw[] = [
  {
    path: 'characters',
    name: 'Characters',
    component: () => import('@/modules/Roleplay/Character/Page/CharactersPage.vue'),
    // Стаб-страница (F10): заглушка показывается и гостю. При реализации волны 4
    // guestAllowed снимается — по §11 ТР гость персонажей не видит.
    meta: { title: 'Персонажи', guestAllowed: true },
  },
]
