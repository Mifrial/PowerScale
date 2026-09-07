import type { RouteRecordRaw } from 'vue-router';

export const adminChildren: RouteRecordRaw[] = [
  {
    path: 'keywords',
    meta: { crumb: () => [{ title: 'Признаки', to: '/admin/keywords' }], requiresAny: ['keyword.view'] },
    children: [
      {
        path: '',
        name: 'Keywords',
        component: () => import('@/modules/Roleplay/Keyword/Page/KeywordsListPage.vue'),
      },
      {
        path: 'new',
        name: 'KeywordNew',
        component: () => import('@/modules/Roleplay/Keyword/Page/KeywordEditPage.vue'),
        meta: {
          title: 'Создание признака',
          crumb: () => [{ title: 'Создание признака' }],
          requiresAny: ['keyword.create'],
        },
      },
      {
        path: ':id/edit',
        name: 'KeywordEdit',
        component: () => import('@/modules/Roleplay/Keyword/Page/KeywordEditPage.vue'),
        meta: {
          title: 'Редактирование признака',
          crumb: () => [{ title: 'Редактирование признака' }],
          requiresAny: ['keyword.edit'],
        },
      },
    ],
  },
];
