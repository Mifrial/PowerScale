import type { RouteLocationNormalizedLoaded, RouteRecordRaw } from 'vue-router';
import type { BreadcrumbItem } from '@/router/BreadcrumbItem';
import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';

function rootCrumb(): BreadcrumbItem[] {
  return [{ title: 'Персонажи', to: '/characters' }];
}

function characterCrumb(to: RouteLocationNormalizedLoaded, suffix: string | null): BreadcrumbItem[] {
  const store = useCharacterStore();
  const name = store.currentCharacter?.character.name || String(to.params.id ?? 'Персонаж');
  const crumbs: BreadcrumbItem[] = [
    { title: 'Персонажи', to: '/characters' },
    { title: name, to: `/characters/${to.params.id}` },
  ];
  if (suffix) crumbs.push({ title: suffix });

  return crumbs;
}

export const routes: RouteRecordRaw[] = [
  {
    path: 'characters',
    name: 'Characters',
    component: () => import('@/modules/Roleplay/Character/Page/CharactersPage.vue'),
    // Гость персонажей не видит (§11 ТР) — guestAllowed снят при реализации списка.
    meta: { title: 'Персонажи', crumb: rootCrumb },
  },
  {
    path: 'characters/new',
    name: 'CharacterNew',
    component: () => import('@/modules/Roleplay/Character/Page/CharactersNewPage.vue'),
    // Настройка создания: пространство + ревизия + лимиты → редактор.
    meta: {
      title: 'Новый персонаж',
      requiresAll: ['character.create'],
      crumb: () => [...rootCrumb(), { title: 'Новый персонаж' }],
    },
  },
  {
    path: 'characters/new/editor',
    name: 'CharacterNewEditor',
    component: () => import('@/modules/Roleplay/Character/Page/CharacterEditPage.vue'),
    // Редактор нового персонажа (черновик без id); настройка создаёт черновик.
    meta: {
      title: 'Редактор персонажа',
      requiresAll: ['character.create'],
      crumb: () => [
        ...rootCrumb(),
        { title: 'Новый персонаж', to: '/characters/new' },
        { title: 'Редактор персонажа' },
      ],
    },
  },
  {
    path: 'characters/:id',
    name: 'CharacterDetail',
    component: () => import('@/modules/Roleplay/Character/Page/CharacterDetailPage.vue'),
    // Без route-perm: доступ проверяется внутри страницы после загрузки (ТР §4 «свой vs чужой»).
    meta: { title: 'Карточка персонажа', crumb: (to) => characterCrumb(to, null) },
  },
  {
    path: 'characters/:id/edit',
    name: 'CharacterEdit',
    component: () => import('@/modules/Roleplay/Character/Page/CharacterEditPage.vue'),
    // Без route-perm: владелец проверяется внутри страницы (как в карточке).
    meta: { title: 'Редактирование персонажа', crumb: (to) => characterCrumb(to, 'Редактирование') },
  },
  {
    path: 'characters/:id/migrate',
    name: 'CharacterMigrate',
    component: () => import('@/modules/Roleplay/Character/Page/CharacterMigratePage.vue'),
    // Без route-perm: владелец проверяется внутри страницы.
    meta: { title: 'Перевод на новую версию правил', crumb: (to) => characterCrumb(to, 'Миграция') },
  },
];
