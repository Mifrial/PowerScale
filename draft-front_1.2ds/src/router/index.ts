import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';
import { useCurrentUser } from '@/modules/Core/User/init';
import { standaloneRoutes } from '@/modules/Core/Auth/routes';
import { moduleChildren } from '@/router/moduleRoutes';
import { evaluateRouteAccess } from '@/router/access';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    ...standaloneRoutes,
    {
      path: '/',
      component: () => import('@/shell/AppShell.vue'),
      meta: { title: 'PowerScale' },
      children: moduleChildren,
    },
  ],
});

let authChecked = false;

router.beforeEach(async (to, _from, next) => {
  const auth = useAuthStore();
  const { currentUser } = useCurrentUser();

  if (!authChecked) {
    authChecked = await auth.checkAuth();
  }

  const decision = evaluateRouteAccess(to, {
    isAuthenticated: auth.isAuthenticated,
    isGuest: auth.isGuest,
    user: currentUser.value,
  });

  if (decision.allow) next();
  else next(decision.redirect);
});

export default router;
