import type { RouteRecordRaw } from 'vue-router';

export const adminChildren: RouteRecordRaw[] = [
  {
    path: 'mechanics',
    meta: { crumb: () => [{ title: 'Механики', to: '/admin/mechanics' }], requiresAny: ['mechanic.view'] },
    children: [
      {
        path: '',
        name: 'Mechanics',
        component: () => import('@/modules/Roleplay/Mechanic/Page/MechanicsListPage.vue'),
      },
      {
        path: 'new',
        name: 'MechanicNew',
        component: () => import('@/modules/Roleplay/Mechanic/Page/MechanicEditPage.vue'),
        meta: {
          title: 'Создание механики',
          crumb: () => [{ title: 'Создание механики' }],
          requiresAny: ['mechanic.create'],
        },
      },
      {
        path: ':id/edit',
        name: 'MechanicEdit',
        component: () => import('@/modules/Roleplay/Mechanic/Page/MechanicEditPage.vue'),
        meta: {
          title: 'Редактирование механики',
          crumb: () => [{ title: 'Редактирование механики' }],
          requiresAny: ['mechanic.edit'],
        },
      },
    ],
  },
];
