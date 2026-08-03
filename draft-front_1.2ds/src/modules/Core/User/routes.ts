import type { RouteRecordRaw } from 'vue-router';
import { useGroupStore } from '@/modules/Core/User/Store/groups';

export const routes: RouteRecordRaw[] = [
  {
    path: 'users',
    meta: { crumb: () => [{ title: 'Пользователи', to: '/users' }], requiresAny: ['user.view'] },
    children: [
      {
        path: '',
        name: 'Users',
        component: () => import('@/modules/Core/User/Page/UsersListPage.vue'),
      },
      {
        path: 'new',
        name: 'UserNew',
        component: () => import('@/modules/Core/User/Page/UserEditPage.vue'),
        meta: {
          title: 'Новый пользователь',
          crumb: () => [{ title: 'Новый пользователь' }],
          requiresAny: ['user.create'],
        },
      },
      {
        path: ':id',
        name: 'UserProfile',
        component: () => import('@/modules/Core/User/Page/UserProfilePage.vue'),
        meta: { title: 'Пользователь', crumb: () => [{ title: 'Профиль' }] },
      },
      {
        path: ':id/edit',
        name: 'UserEdit',
        component: () => import('@/modules/Core/User/Page/UserEditPage.vue'),
        meta: { title: 'Редактирование', crumb: () => [{ title: 'Редактирование' }], requiresAny: ['user.edit'] },
      },
    ],
  },
];

export const adminChildren: RouteRecordRaw[] = [
  {
    path: '',
    name: 'Admin',
    component: () => import('@/modules/Core/User/Page/AdminDashboard.vue'),
    meta: { admin: true },
  },
  {
    path: 'groups',
    meta: { crumb: () => [{ title: 'Группы пользователей', to: '/admin/groups' }], requiresAny: ['user_group.view'] },
    children: [
      {
        path: '',
        name: 'Groups',
        component: () => import('@/modules/Core/User/Page/GroupsListPage.vue'),
      },
      {
        path: 'new',
        name: 'GroupNew',
        component: () => import('@/modules/Core/User/Page/GroupEditPage.vue'),
        meta: {
          title: 'Создание группы',
          crumb: () => [{ title: 'Создание группы' }],
          requiresAny: ['user_group.create'],
        },
      },
      {
        path: ':id',
        name: 'GroupDetail',
        component: () => import('@/modules/Core/User/Page/GroupDetailPage.vue'),
        meta: {
          title: 'Группа',
          crumb: () => [{ title: useGroupStore().currentGroup?.name ?? 'Группа' }],
        },
      },
      {
        path: ':id/edit',
        name: 'GroupEdit',
        component: () => import('@/modules/Core/User/Page/GroupEditPage.vue'),
        meta: {
          title: 'Редактирование группы',
          crumb: () => {
            const name = useGroupStore().currentGroup?.name;

            return [{ title: name ? `Редактирование: ${name}` : 'Редактирование группы' }];
          },
          requiresAny: ['user_group.edit'],
        },
      },
    ],
  },
];
