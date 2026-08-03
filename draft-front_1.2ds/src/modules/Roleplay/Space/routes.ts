import type { RouteLocationNormalizedLoaded, RouteRecordRaw } from 'vue-router';
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces';

function spaceRoot() {
  return [{ title: 'Пространства', to: '/spaces' }];
}

function spaceName(to: RouteLocationNormalizedLoaded): string {
  const store = useSpaceStore();

  return store.currentSpace?.name || String(to.params.code);
}

function ctxCrumb(to: RouteLocationNormalizedLoaded) {
  const ctx = String(to.params.ctx);
  const title = ctx === 'draft' ? 'Черновик' : `v${ctx}`;

  return [{ title, to: `/space/${to.params.code}/${ctx}` }];
}

export function createSpaceRoutes(ruleCtxChildren: RouteRecordRaw[]): RouteRecordRaw[] {
  return [
    {
      path: 'spaces',
      meta: { crumb: spaceRoot, guestAllowed: true },
      children: [
        {
          path: '',
          name: 'Spaces',
          component: () => import('@/modules/Roleplay/Space/Page/SpacesPage.vue'),
        },
        {
          path: 'new',
          name: 'SpaceNew',
          component: () => import('@/modules/Roleplay/Space/Page/SpaceNewPage.vue'),
          meta: {
            title: 'Создание пространства',
            crumb: () => [{ title: 'Создание пространства' }],
            requiresAny: ['space.create'],
          },
        },
      ],
    },
    {
      path: 'space',
      meta: { crumb: spaceRoot },
      children: [
        {
          path: ':code',
          meta: {
            crumb: (to) => [{ title: spaceName(to), to: `/space/${to.params.code}` }],
          },
          children: [
            {
              path: '',
              name: 'SpaceLanding',
              component: () => import('@/modules/Roleplay/Space/Page/SpaceDetailPage.vue'),
            },
            {
              path: ':ctx',
              meta: { crumb: ctxCrumb },
              children: [
                {
                  path: '',
                  name: 'SpaceDetail',
                  component: () => import('@/modules/Roleplay/Space/Page/SpaceDetailPage.vue'),
                },
                ...ruleCtxChildren,
              ],
            },
            {
              path: 'settings',
              name: 'SpaceSettings',
              component: () => import('@/modules/Roleplay/Space/Page/SpaceSettingsPage.vue'),
              meta: {
                title: 'Настройки пространства',
                crumb: () => [{ title: 'Настройки' }],
              },
            },
          ],
        },
      ],
    },
  ];
}
