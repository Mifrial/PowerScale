import type { RouteRecordRaw } from 'vue-router'

export const standaloneRoutes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('./Page/LoginPage.vue'),
    meta: { layout: 'auth', title: 'Вход' },
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('./Page/RegisterPage.vue'),
    meta: { layout: 'auth', title: 'Регистрация' },
  },
  {
    path: '/forgot-password',
    name: 'ForgotPassword',
    component: () => import('./Page/ForgotPasswordPage.vue'),
    meta: { layout: 'auth', title: 'Восстановление пароля' },
  },
  {
    path: '/reset-password',
    name: 'ResetPassword',
    component: () => import('./Page/ResetPasswordPage.vue'),
    meta: { layout: 'auth', title: 'Сброс пароля' },
  },
]
