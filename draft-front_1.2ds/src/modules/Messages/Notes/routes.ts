import type { RouteRecordRaw } from 'vue-router';

export const routes: RouteRecordRaw[] = [
  {
    path: 'notes',
    name: 'Notes',
    component: () => import('@/modules/Messages/Notes/Page/NotesPage.vue'),
    meta: { title: 'Заметки' },
  },
];
