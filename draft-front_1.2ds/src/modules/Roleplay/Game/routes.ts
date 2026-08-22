import type { RouteLocationNormalizedLoaded, RouteRecordRaw } from 'vue-router';
import type { BreadcrumbItem } from '@/router/BreadcrumbItem';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';

function rootCrumb(): BreadcrumbItem[] {
  return [{ title: 'Игры', to: '/games' }];
}

function gameCrumb(to: RouteLocationNormalizedLoaded, suffix: string | null): BreadcrumbItem[] {
  const store = useGameStore();
  const name = store.currentGame?.game.name || String(to.params.id ?? 'Игра');
  const crumbs: BreadcrumbItem[] = [
    { title: 'Игры', to: '/games' },
    { title: name, to: `/games/${to.params.id}` },
  ];
  if (suffix) crumbs.push({ title: suffix });

  return crumbs;
}

export const routes: RouteRecordRaw[] = [
  {
    path: 'games',
    name: 'Games',
    component: () => import('@/modules/Roleplay/Game/Page/GamesPage.vue'),
    meta: { title: 'Игры', guestAllowed: true, crumb: rootCrumb },
  },
  {
    path: 'games/new',
    name: 'GameNew',
    component: () => import('@/modules/Roleplay/Game/Page/GameNewPage.vue'),
    meta: {
      title: 'Новая игра',
      requiresAll: ['game.create'],
      crumb: () => [...rootCrumb(), { title: 'Новая игра' }],
    },
  },
  {
    path: 'games/:id',
    name: 'GameDetail',
    component: () => import('@/modules/Roleplay/Game/Page/GameDetailPage.vue'),
    // Без route-perm: доступ проверяется внутри страницы после загрузки (статус/видимость/участие).
    meta: { title: 'Карточка игры', crumb: (to) => gameCrumb(to, null) },
  },
  {
    path: 'games/:id/edit',
    name: 'GameEdit',
    component: () => import('@/modules/Roleplay/Game/Page/GameEditPage.vue'),
    // Без route-perm: право редактирования (владелец/ведущие/per-game) проверяется внутри страницы.
    meta: { title: 'Настройки игры', crumb: (to) => gameCrumb(to, 'Настройки') },
  },
  {
    path: 'games/:id/npcs/:npcId/edit',
    name: 'NpcEdit',
    component: () => import('@/modules/Roleplay/Game/Page/NpcEditPage.vue'),
    // Без route-perm: право редактирования НПС (canEditGame) проверяется внутри страницы.
    meta: { title: 'Лист НПС', crumb: (to) => gameCrumb(to, 'Лист НПС') },
  },
  {
    path: 'games/:id/characters/new',
    name: 'GameCharacterNew',
    component: () => import('@/modules/Roleplay/Game/Page/GameCharacterNewPage.vue'),
    // Без route-perm: участие в игре проверяется внутри страницы (создание «через игру»).
    meta: { title: 'Новый персонаж игры', crumb: (to) => gameCrumb(to, 'Новый персонаж') },
  },
];
