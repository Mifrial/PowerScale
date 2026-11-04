import type { RouteRecordRaw } from 'vue-router';

export const adminChildren: RouteRecordRaw[] = [
  {
    path: 'files',
    meta: { crumb: () => [{ title: 'Файлы', to: '/admin/files' }], requiresAny: ['file.view'] },
    children: [
      {
        path: '',
        name: 'Files',
        component: () => import('@/modules/Core/File/Page/FileListPage.vue'),
      },
    ],
  },
];
