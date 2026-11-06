import type { RouteRecordRaw } from 'vue-router';

export const routes: RouteRecordRaw[] = [
  {
    path: 'maps',
    name: 'Maps',
    component: () => import('@/modules/Roleplay/Map/Page/MapsListPage.vue'),
    meta: {
      title: 'Карты',
      crumb: () => [{ title: 'Карты', to: '/maps' }],
    },
  },
  {
    path: 'maps/:id',
    name: 'MapDetail',
    component: () => import('@/modules/Roleplay/Map/Page/MapDetailPage.vue'),
    // Доступ проверяется внутри страницы: чужая приватная карта отвечает MAP_DENIED.
    meta: {
      title: 'Карта',
      crumb: (to) => [{ title: 'Карты', to: '/maps' }, { title: `Карта #${to.params.id ?? ''}` }],
    },
  },
];
