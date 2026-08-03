import type { RouteRecordRaw } from 'vue-router'

export const routes: RouteRecordRaw[] = [
  {
    path: 'notifications',
    name: 'Notifications',
    component: () => import('@/modules/Messages/Notifications/Page/NotificationsPage.vue'),
    meta: { title: 'Уведомления' },
  },
]

export const adminChildren: RouteRecordRaw[] = [
  {
    path: 'notification-templates',
    meta: { crumb: () => [{ title: 'Шаблоны уведомлений', to: '/admin/notification-templates' }], requiresAny: ['notification_template.view'] },
    children: [
      {
        path: '',
        name: 'NotificationTemplates',
        component: () => import('@/modules/Messages/Notifications/Page/TemplatesListPage.vue'),
      },
      {
        path: 'new',
        name: 'TemplateNew',
        component: () => import('@/modules/Messages/Notifications/Page/TemplateEditPage.vue'),
        meta: { title: 'Создание шаблона', crumb: () => [{ title: 'Создание шаблона' }], requiresAny: ['notification_template.create'] },
      },
      {
        path: ':id/edit',
        name: 'TemplateEdit',
        component: () => import('@/modules/Messages/Notifications/Page/TemplateEditPage.vue'),
        meta: { title: 'Редактирование шаблона', crumb: () => [{ title: 'Редактирование шаблона' }], requiresAny: ['notification_template.edit'] },
      },
    ],
  },
]
