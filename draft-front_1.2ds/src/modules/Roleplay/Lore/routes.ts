import type { RouteLocationNormalizedLoaded, RouteRecordRaw } from 'vue-router';
import type { BreadcrumbItem } from '@/router/BreadcrumbItem';
import { useLoreCrumbStore } from '@/modules/Roleplay/Lore/Store/crumbs';

function worldCodeOf(to: RouteLocationNormalizedLoaded): string {
  return String(to.params.worldCode ?? '');
}

function articleCodeOf(to: RouteLocationNormalizedLoaded): string {
  return String(to.params.articleCode ?? '');
}

/** Крошки «Кодекс → <мир>»; название мира берём из стора, пока грузится — код. */
function worldCrumbs(to: RouteLocationNormalizedLoaded): BreadcrumbItem[] {
  const worldCode = worldCodeOf(to);
  const worldTitle = useLoreCrumbStore().getWorldTitle(worldCode) ?? worldCode;

  return [
    { title: 'Кодекс', to: '/lore' },
    { title: worldTitle, to: `/lore/${worldCode}` },
  ];
}

function articleCrumbs(to: RouteLocationNormalizedLoaded, suffix: string | null): BreadcrumbItem[] {
  const worldCode = worldCodeOf(to);
  const articleCode = articleCodeOf(to);
  const articlesPath = `/lore/${worldCode}/article/${articleCode}`;
  const articleTitle = useLoreCrumbStore().getArticleTitle(worldCode, articleCode) ?? articleCode;
  const crumbs: BreadcrumbItem[] = [...worldCrumbs(to), { title: articleTitle, to: articlesPath }];
  if (suffix) crumbs.push({ title: suffix });

  return crumbs;
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
      crumb: (to) => articleCrumbs(to, 'Редактирование'),
    },
  },
  {
    path: 'lore/:worldCode/article/:articleCode',
    name: 'LoreArticle',
    component: () => import('@/modules/Roleplay/Lore/Page/LoreArticlePage.vue'),
    meta: {
      title: 'Статья',
      crumb: (to) => articleCrumbs(to, null),
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
