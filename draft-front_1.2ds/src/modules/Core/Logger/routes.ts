import type { RouteRecordRaw } from 'vue-router';

export const adminChildren: RouteRecordRaw[] = [
  {
    path: 'logs',
    meta: { crumb: () => [{ title: 'Журнал', to: '/admin/logs' }], requiresAny: ['logger.view'] },
    children: [
      {
        path: '',
        name: 'Logs',
        component: () => import('@/modules/Core/Logger/Page/LoggerListPage.vue'),
      },
    ],
  },
];
