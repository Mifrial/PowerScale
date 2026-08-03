import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/modules/Core/Auth/Store/auth'
import { useUserStore } from '@/modules/Core/User/Store/users'
import { standaloneRoutes } from '@/modules/Core/Auth/routes'
import { moduleChildren } from '@/router/moduleRoutes'
import { evaluateRouteAccess } from '@/router/access'

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
  const userStore = useUserStore()

  if (!authChecked) {
    authChecked = await auth.checkAuth()
  }

  const decision = evaluateRouteAccess(to, {
    isAuthenticated: auth.isAuthenticated,
    isGuest: auth.isGuest,
    user: userStore.currentUser,
  })

  if (decision.allow) next()
  else next(decision.redirect)
})

export default router
