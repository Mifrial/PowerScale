import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { UpdateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/UpdateCharacterData';
import type { AddCustomRuleData } from '@/modules/Roleplay/Character/Dto/AddCustomRuleData';
import type { UpdateCustomRuleData } from '@/modules/Roleplay/Character/Dto/UpdateCustomRuleData';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import {
  versions,
  fetchCharacter,
  syncCharacterVersion,
  updateCharacter as mockUpdateCharacter,
  addCustomRule as mockAddCustomRule,
  updateCustomRule as mockUpdateCustomRule,
  appendCustomRule,
  updateCustomRuleInVersion,
} from '@/modules/Roleplay/Character/Mock/mockCharacters';
import {
  gameCharacterMemberships,
  syncCharacterVersionToMemberships,
  isSessionActive,
} from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import {
  combatKey,
  writeOverlaySheet,
  getStoredCombatOverlay,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';

/**
 * Одиночный роутер обновлений персонажа (модель версий — Баг 1, 2026-08-20): один
 * `updateCharacter` сам решает, куда писать. Изменения во время активной сессии
 * (approved + игра playing) — в сессионный оверлей; все остальные — в latest (`versions[id]`)
 * с автоподачей на модерацию. Избегает круговых импортов: роутер импортируют mockCharacterApi
 * и mockGameLoot, а mockCharacters остаётся «чистым» хранилищем версий.
 */

interface SessionTarget {
  gameId: number;
  characterId: number;
  activeVersion: CharacterVersion | null;
}

/** Членство-цель активной сессии: approved + игра playing (по явному gameId или «текущей сессии»). */
export function sessionTarget(characterId: number, gameId?: number): SessionTarget | null {
  const candidates = gameCharacterMemberships.filter(
    (membership) => membership.characterId === characterId && membership.membershipStatus === 'approved',
  );
  const target =
    gameId !== undefined
      ? (candidates.find((membership) => membership.gameId === gameId) ?? null)
      : (candidates.find((membership) => isSessionActive(membership.gameId)) ?? null);
  if (!target || !isSessionActive(target.gameId)) return null;

  return { gameId: target.gameId, characterId: target.characterId, activeVersion: target.activeVersion };
}

/** После изменения latest: перепривязка кэша листа + автоподача членств. */
export function applyVersionChange(characterId: number): void {
  syncCharacterVersion(characterId);
  syncCharacterVersionToMemberships(characterId);
}

/**
 * Обновление персонажа. `context.gameId` — из in-game редактора; без него (standalone-карточка)
 * изменение всегда идёт в latest. Возвращает деталь листа (latest) — карточка не меняется при
 * записи в оверлей, живые правки живут в игре (approved + overlay).
 */
export async function updateCharacter(
  id: number,
  data: UpdateCharacterData,
  _signal?: AbortSignal,
): Promise<CharacterDetail> {
  const target = sessionTarget(id, data.gameId);
  if (target) {
    await writeOverlaySheet(target.gameId, combatKey('character', id), data.version);

    return fetchCharacter(id);
  }

  const detail = await mockUpdateCharacter(id, data);
  applyVersionChange(id);

  return detail;
}

/**
 * Выдача кастомного правила ведущим «на ходу»: во время активной сессии правило уходит в оверлей
 * (видно в игре сразу), иначе — в latest с автоподачей.
 */
export async function addCustomRule(
  id: number,
  data: AddCustomRuleData,
  _signal?: AbortSignal,
): Promise<CharacterDetail> {
  const target = sessionTarget(id);
  if (target) {
    const version = await overlaySheetBase(target, id);
    await writeOverlaySheet(target.gameId, combatKey('character', id), appendCustomRule(version, data));

    return fetchCharacter(id);
  }

  const detail = await mockAddCustomRule(id, data);
  applyVersionChange(id);

  return detail;
}

/**
 * Правка/замена записи кастомного правила: во время активной сессии — в оверлей, иначе —
 * в latest с автоподачей.
 */
export async function updateCustomRule(
  id: number,
  entryId: number,
  data: UpdateCustomRuleData,
  _signal?: AbortSignal,
): Promise<CharacterDetail> {
  const target = sessionTarget(id);
  if (target) {
    const version = await overlaySheetBase(target, id);
    await writeOverlaySheet(
      target.gameId,
      combatKey('character', id),
      await updateCustomRuleInVersion(version, entryId, data),
    );

    return fetchCharacter(id);
  }

  const detail = await mockUpdateCustomRule(id, entryId, data);
  applyVersionChange(id);

  return detail;
}

/** База оверлейного листа для правок: существующий sheet или копия активной версии. */
export async function overlaySheetBase(target: SessionTarget, characterId: number): Promise<CharacterVersion> {
  const stored = getStoredCombatOverlay(target.gameId, combatKey('character', characterId));
  if (stored?.sheet) return stored.sheet;
  const active = target.activeVersion ?? versions[characterId];
  if (!active) throw new Error(`Character ${characterId} not found`);

  return JSON.parse(JSON.stringify(active)) as CharacterVersion;
}
