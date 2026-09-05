import type { RouteLocationNormalizedLoaded, RouteRecordRaw } from 'vue-router';
import { useSpaceStore } from '@/modules/Roleplay/RuleSpace/Store/spaces';

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

export function createRuleSpaceRoutes(ruleCtxChildren: RouteRecordRaw[]): RouteRecordRaw[] {
  return [
    {
      path: 'spaces',
      meta: { crumb: spaceRoot },
      children: [
        {
          path: '',
          name: 'Spaces',
          component: () => import('@/modules/Roleplay/RuleSpace/Page/SpacesPage.vue'),
        },
        {
          path: 'new',
          name: 'SpaceNew',
          component: () => import('@/modules/Roleplay/RuleSpace/Page/SpaceNewPage.vue'),
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
          component: () => import('@/modules/Roleplay/RuleSpace/Component/SpaceContextLayout.vue'),
          meta: {
            crumb: (to) => [{ title: spaceName(to), to: `/space/${to.params.code}` }],
          },
          children: [
            {
              path: '',
              name: 'SpaceLanding',
              component: () => import('@/modules/Roleplay/RuleSpace/Page/SpaceLandingPage.vue'),
            },
            {
              path: ':ctx',
              meta: { crumb: ctxCrumb },
              children: [
                {
                  path: '',
                  name: 'SpaceDetail',
                  component: () => import('@/modules/Roleplay/RuleSpace/Page/SpaceDetailPage.vue'),
                },
                ...ruleCtxChildren,
              ],
            },
            {
              path: 'settings',
              name: 'SpaceSettings',
              component: () => import('@/modules/Roleplay/RuleSpace/Page/SpaceSettingsPage.vue'),
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
