import type { RouteRecordRaw } from 'vue-router';

export const routes: RouteRecordRaw[] = [
  {
    path: 'scenes',
    name: 'Scenes',
    component: () => import('@/modules/Roleplay/Scene/Page/ScenesListPage.vue'),
    meta: {
      title: 'Локации',
      crumb: () => [{ title: 'Локации', to: '/scenes' }],
    },
  },
  {
    path: 'scenes/:id',
    name: 'SceneDetail',
    component: () => import('@/modules/Roleplay/Scene/Page/SceneDetailPage.vue'),
    meta: {
      title: 'Локация',
      crumb: (to) => [{ title: 'Локации', to: '/scenes' }, { title: `Локация #${to.params.id ?? ''}` }],
    },
  },
];
