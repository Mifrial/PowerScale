import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { defineAsyncComponent } from 'vue';
import type { ICharacterApi } from '@/modules/Roleplay/Character/Interface/ICharacterApi';
import type { SheetRole } from '@/modules/Roleplay/Character/Interface/SheetRole';
import type { CharacterCardExtension } from '@/modules/Roleplay/Character/Interface/CharacterCardExtension';
import type { IInGameSheetSource } from '@/modules/Roleplay/Character/Interface/IInGameSheetSource';
import type { ICharacterSessionOverlay } from '@/modules/Roleplay/Character/Interface/ICharacterSessionOverlay';
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
export { useAttackFavoritesStore } from '@/modules/Roleplay/Character/Store/attackFavorites';

// Публичный редактор листа (персонаж/НПС). Асинхронный — init.ts не тянет .vue/CSS
// при импорте в node-тестах (routes → store → init) и код-сплитится по месту использования.
export const CharacterSheetEditor = defineAsyncComponent(
  () => import('@/modules/Roleplay/Character/Component/Editor/CharacterSheetEditor.vue'),
);

export const SheetCard = defineAsyncComponent(() => import('@/modules/Roleplay/Character/Component/SheetCard.vue'));

export const CharacterCombatSheet = defineAsyncComponent(
  () => import('@/modules/Roleplay/Character/Component/Combat/CharacterCombatSheet.vue'),
);

export const RuleLink = defineAsyncComponent(
  () => import('@/modules/Roleplay/Character/Component/Detail/RuleLink.vue'),
);

export const UniqueRulesTab = defineAsyncComponent(
  () => import('@/modules/Roleplay/Character/Component/Detail/UniqueRulesTab.vue'),
);

export const OwnerNotesDialog = defineAsyncComponent(
  () => import('@/modules/Roleplay/Character/Component/OwnerNotesDialog.vue'),
);

export const MigrationReport = defineAsyncComponent(
  () => import('@/modules/Roleplay/Character/Component/MigrationReport.vue'),
);

// Инъекция ролей видимости листа: сторонние модули (например Game) регистрируют
// generic-роли; Character не знает их семантики (см. SheetAccessService).
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

let inGameSheetSource: IInGameSheetSource | null = null;

export function registerInGameSheetSource(source: IInGameSheetSource): void {
  inGameSheetSource = source;
}

export function getInGameSheetSource(): IInGameSheetSource | null {
  return inGameSheetSource;
}

let characterSessionOverlay: ICharacterSessionOverlay | null = null;

export function registerCharacterSessionOverlay(overlay: ICharacterSessionOverlay): void {
  characterSessionOverlay = overlay;
}

export function getCharacterSessionOverlay(): ICharacterSessionOverlay | null {
  return characterSessionOverlay;
}

export function registerCharacterModule(): void {
  registerPermissionCategory(CHARACTER_PERMISSION_CATEGORY);
  registerChatTypes(CHARACTER_CHAT_TYPES);
  registerChatTabs(CHARACTER_CHAT_TABS);
  // Правила обсуждения персонажа: ревизия персонажа (чипы/ссылки/броски в мессенджере).
  registerChatRulesProvider(characterChatRulesProvider);
}

export { characterAccessService } from '@/modules/Roleplay/Character/Service/Instance/characterAccessService';
export { sheetAccessService } from '@/modules/Roleplay/Character/Service/Instance/sheetAccessService';
export { characterSheetValidationService } from '@/modules/Roleplay/Character/Service/Instance/characterSheetValidationService';
export { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/Service/Instance/stateRuntimeEffectsService';
export { itemCheckAdvantagesService } from '@/modules/Roleplay/Character/Service/Instance/itemCheckAdvantagesService';
export { abilityCheckAdvantagesService } from '@/modules/Roleplay/Character/Service/Instance/abilityCheckAdvantagesService';
export { liveActionPointsLimitService } from '@/modules/Roleplay/Character/Service/Instance/liveActionPointsLimitService';
export { racialInnateGearService } from '@/modules/Roleplay/Character/Service/Instance/racialInnateGearService';
export { weaponProficiencyService } from '@/modules/Roleplay/Character/Service/Instance/weaponProficiencyService';
export { editorStatViewsService } from '@/modules/Roleplay/Character/Service/Instance/editorStatViewsService';
export { itemWeaponProfilesService } from '@/modules/Roleplay/Character/Service/Instance/itemWeaponProfilesService';
export { itemMasteryService } from '@/modules/Roleplay/Character/Service/Instance/itemMasteryService';
export { weaponAttackRangeService } from '@/modules/Roleplay/Character/Service/Instance/weaponAttackRangeService';
export { characterChatRulesContextService } from '@/modules/Roleplay/Character/Service/Instance/characterChatRulesContextService';
export { characterOverviewService } from '@/modules/Roleplay/Character/Service/Instance/characterOverviewService';
export { characterMigrationService } from '@/modules/Roleplay/Character/Service/Instance/characterMigrationService';
export { characterVersionIntegrityService } from '@/modules/Roleplay/Character/Service/Instance/characterVersionIntegrityService';
export { movementContextService } from '@/modules/Roleplay/Character/Service/Instance/movementContextService';
export { DEFAULT_FALLOFF } from '@/modules/Roleplay/Character/Constant/Weapon/DEFAULT_FALLOFF';
