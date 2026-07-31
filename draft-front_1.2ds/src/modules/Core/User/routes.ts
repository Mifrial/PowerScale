import type { RouteRecordRaw } from 'vue-router'
import { useGroupStore } from './Store/groups'

export const routes: RouteRecordRaw[] = [
  {
    path: 'users',
    meta: { crumb: () => [{ title: 'Пользователи', to: '/users' }] },
    children: [
      {
        path: '',
        name: 'Users',
        component: () => import('./Page/UsersListPage.vue'),
      },
      {
        path: 'new',
        name: 'UserNew',
        component: () => import('./Page/UserEditPage.vue'),
        meta: { title: 'Новый пользователь', crumb: () => [{ title: 'Новый пользователь' }] },
      },
      {
        path: ':id',
        name: 'UserProfile',
        component: () => import('./Page/UserProfilePage.vue'),
        meta: { title: 'Пользователь', crumb: () => [{ title: 'Профиль' }] },
      },
      {
        path: ':id/edit',
        name: 'UserEdit',
        component: () => import('./Page/UserEditPage.vue'),
        meta: { title: 'Редактирование', crumb: () => [{ title: 'Редактирование' }] },
      },
    ],
  },
]

export const adminChildren: RouteRecordRaw[] = [
  {
    path: '',
    name: 'Admin',
    component: () => import('./Page/AdminDashboard.vue'),
  },
  {
    path: 'groups',
    meta: { crumb: () => [{ title: 'Группы пользователей', to: '/admin/groups' }] },
    children: [
      {
        path: '',
        name: 'Groups',
        component: () => import('./Page/GroupsListPage.vue'),
      },
      {
        path: 'new',
        name: 'GroupNew',
        component: () => import('./Page/GroupEditPage.vue'),
        meta: { title: 'Создание группы', crumb: () => [{ title: 'Создание группы' }] },
      },
      {
        path: ':id',
        name: 'GroupDetail',
        component: () => import('./Page/GroupDetailPage.vue'),
        meta: {
          title: 'Группа',
          crumb: () => [{ title: useGroupStore().currentGroup?.name ?? 'Группа' }],
        },
      },
      {
        path: ':id/edit',
        name: 'GroupEdit',
        component: () => import('./Page/GroupEditPage.vue'),
        meta: {
          title: 'Редактирование группы',
          crumb: () => {
            const name = useGroupStore().currentGroup?.name
            return [{ title: name ? `Редактирование: ${name}` : 'Редактирование группы' }]
          },
        },
      },
    ],
  },
]
