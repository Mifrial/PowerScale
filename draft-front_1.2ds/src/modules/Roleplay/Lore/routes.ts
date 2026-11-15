import type { RouteLocationNormalizedLoaded, RouteRecordRaw } from 'vue-router';

function worldCrumbs(to: RouteLocationNormalizedLoaded) {
  return [
    { title: 'Кодекс', to: '/lore' },
    { title: String(to.params.worldCode ?? ''), to: `/lore/${String(to.params.worldCode ?? '')}` },
  ];
}

export const routes: RouteRecordRaw[] = [
  {
    path: 'lore',
    name: 'LoreWorlds',
    component: () => import('@/modules/Roleplay/Lore/Page/LoreWorldsPage.vue'),
    meta: {
      title: 'Кодекс',
      crumb: () => [{ title: 'Кодекс', to: '/lore' }],
    },
  },
  {
    path: 'lore/:worldCode/article/new/edit',
    name: 'LoreArticleNew',
    component: () => import('@/modules/Roleplay/Lore/Page/LoreArticleEditPage.vue'),
    meta: {
      title: 'Новая статья',
      crumb: (to) => [...worldCrumbs(to), { title: 'Новая статья' }],
    },
  },
  {
    path: 'lore/:worldCode/settings',
    name: 'LoreWorldSettings',
    component: () => import('@/modules/Roleplay/Lore/Page/LoreWorldSettingsPage.vue'),
    meta: {
      title: 'Настройки мира',
      crumb: (to) => [...worldCrumbs(to), { title: 'Настройки' }],
    },
  },
  {
    path: 'lore/:worldCode/article/:articleCode/edit',
    name: 'LoreArticleEdit',
    component: () => import('@/modules/Roleplay/Lore/Page/LoreArticleEditPage.vue'),
    meta: {
      title: 'Редактирование статьи',
      crumb: (to) => [
        ...worldCrumbs(to),
        {
          title: String(to.params.articleCode ?? ''),
          to: `/lore/${String(to.params.worldCode ?? '')}/article/${String(to.params.articleCode ?? '')}`,
        },
        { title: 'Редактирование' },
      ],
    },
  },
  {
    path: 'lore/:worldCode/article/:articleCode',
    name: 'LoreArticle',
    component: () => import('@/modules/Roleplay/Lore/Page/LoreArticlePage.vue'),
    meta: {
      title: 'Статья',
      crumb: (to) => [...worldCrumbs(to), { title: String(to.params.articleCode ?? '') }],
    },
  },
  {
    path: 'lore/:worldCode',
    name: 'LoreWorld',
    component: () => import('@/modules/Roleplay/Lore/Page/LoreWorldPage.vue'),
    meta: {
      title: 'Мир кодекса',
      crumb: worldCrumbs,
    },
  },
];
