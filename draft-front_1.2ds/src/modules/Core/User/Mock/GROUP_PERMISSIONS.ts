/**
 * Единый справочник «группа → permission-ключи» для mock-режима.
 * Канонические имена групп — из ТР §4: Администраторы / Игрок / Ведущий.
 * «Администраторы» — все зарегистрированные модулями ключи (реестр прав Core/User);
 * вычисляется лениво в `groupPermissions`, не здесь.
 */
export const GROUP_PERMISSIONS: Record<string, string[]> = {
  Игрок: ['user.view', 'character.create', 'game.create'],
  Ведущий: ['user.view', 'character.create', 'character.view', 'space.view_all', 'rule.view', 'game.create'],
};
