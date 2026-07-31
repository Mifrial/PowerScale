import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/modules/Core/Auth/Store/auth'
import { standaloneRoutes } from '@/modules/Core/Auth/routes'
import { moduleChildren } from './moduleRoutes'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    ...standaloneRoutes,
    {
      path: '/',
      component: () => import('@/shell/Shell.vue'),
      meta: { title: 'PowerScale' },
      children: moduleChildren,
    },
  ],
})

let authChecked = false

router.beforeEach(async (to, _from, next) => {
  const auth = useAuthStore()

  if (!authChecked) {
    await auth.checkAuth()
    authChecked = true
  }

  const publicRoutes = ['Login', 'Register', 'ForgotPassword', 'ResetPassword']
  if (to.meta.layout === 'auth') {
    if (auth.isAuthenticated && !auth.isGuest) {
      next({ name: 'Dashboard' })
    } else {
      next()
    }
  } else if (!auth.isAuthenticated) {
    next({ name: 'Login' })
  } else {
    next()
  }
})

export default router
