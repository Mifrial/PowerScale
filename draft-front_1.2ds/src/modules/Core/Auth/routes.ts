import type { RouteRecordRaw } from 'vue-router'

export const standaloneRoutes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/modules/Core/Auth/Page/LoginPage.vue'),
    meta: { layout: 'auth', title: 'Вход' },
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('@/modules/Core/Auth/Page/RegisterPage.vue'),
    meta: { layout: 'auth', title: 'Регистрация' },
  },
  {
    path: '/forgot-password',
    name: 'ForgotPassword',
    component: () => import('@/modules/Core/Auth/Page/ForgotPasswordPage.vue'),
    meta: { layout: 'auth', title: 'Восстановление пароля' },
  },
  {
    path: '/reset-password',
    name: 'ResetPassword',
    component: () => import('@/modules/Core/Auth/Page/ResetPasswordPage.vue'),
    meta: { layout: 'auth', title: 'Сброс пароля' },
  },
]
