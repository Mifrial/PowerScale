import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { defineAsyncComponent } from 'vue';
import type { ICharacterApi } from '@/modules/Roleplay/Character/Interface/ICharacterApi';
import type { SheetRole } from '@/modules/Roleplay/Character/Interface/SheetRole';
import type { CharacterCardExtension } from '@/modules/Roleplay/Character/Interface/CharacterCardExtension';
import { registerPermissionCategory } from '@/modules/Core/User/init';
import { registerChatTypes, registerChatTabs, registerChatRulesProvider } from '@/modules/Messages/Chat/init';
import { CHARACTER_PERMISSION_CATEGORY } from '@/modules/Roleplay/Character/Constant/permissions';
import { CHARACTER_CHAT_TYPES } from '@/modules/Roleplay/Character/Constant/Chat/CHARACTER_CHAT_TYPES';
import { CHARACTER_CHAT_TABS } from '@/modules/Roleplay/Character/Constant/Chat/CHARACTER_CHAT_TABS';
import { characterChatRulesProvider } from '@/modules/Roleplay/Character/Chat/characterChatRulesProvider';

export function registerCharacterApi(api: ICharacterApi): void {
  serviceLocator.set('Roleplay.Character.Service.CharacterApi', api);
}

export function getCharacterApi(): ICharacterApi {
  return serviceLocator.get('Roleplay.Character.Service.CharacterApi');
}

export { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
export { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';

// Публичный редактор листа (персонаж/НПС). Асинхронный — init.ts не тянет .vue/CSS
// при импорте в node-тестах (routes → store → init) и код-сплитится по месту использования.
export const CharacterSheetEditor = defineAsyncComponent(
  () => import('@/modules/Roleplay/Character/Component/Editor/CharacterSheetEditor.vue'),
);

// Инъекция ролей видимости листа: сторонние модули (например Game) регистрируют
// generic-роли; Character не знает их семантики (см. Utils/sheetAccess).
const sheetRoles: SheetRole[] = [];

export function registerSheetRole(role: SheetRole): void {
  if (!sheetRoles.some((existing) => existing.name === role.name)) {
    sheetRoles.push(role);
  }
}

export function getSheetRoles(): SheetRole[] {
  return sheetRoles;
}

/** Сброс инжектированных ролей (тесты). */
export function resetSheetRoles(): void {
  sheetRoles.splice(0);
}

// Расширения карточки персонажа: модули-доноры регистрируют UI-блоки.
const characterCardExtensions: CharacterCardExtension[] = [];

export function registerCharacterCardExtension(extension: CharacterCardExtension): void {
  if (!characterCardExtensions.some((existing) => existing.id === extension.id)) {
    characterCardExtensions.push(extension);
  }
}

export function getCharacterCardExtensions(): CharacterCardExtension[] {
  return characterCardExtensions;
}

export function registerCharacterModule(): void {
  registerPermissionCategory(CHARACTER_PERMISSION_CATEGORY);
  registerChatTypes(CHARACTER_CHAT_TYPES);
  registerChatTabs(CHARACTER_CHAT_TABS);
  // Правила обсуждения персонажа: ревизия персонажа (чипы/ссылки/броски в мессенджере).
  registerChatRulesProvider(characterChatRulesProvider);
}
