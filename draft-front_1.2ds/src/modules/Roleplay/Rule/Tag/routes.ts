import type { RouteRecordRaw } from 'vue-router'

export const adminChildren: RouteRecordRaw[] = [
  {
    path: 'tags',
    meta: { crumb: () => [{ title: 'Теги', to: '/admin/tags' }] },
    children: [
      {
        path: '',
        name: 'Tags',
        component: () => import('./Page/TagsListPage.vue'),
      },
      {
        path: 'new',
        name: 'TagNew',
        component: () => import('./Page/TagEditPage.vue'),
        meta: { title: 'Создание тега', crumb: () => [{ title: 'Создание тега' }] },
      },
      {
        path: ':id/edit',
        name: 'TagEdit',
        component: () => import('./Page/TagEditPage.vue'),
        meta: { title: 'Редактирование тега', crumb: () => [{ title: 'Редактирование тега' }] },
      },
    ],
  },
]
