import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { UpdateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/UpdateCharacterData';
import type { AddCustomRuleData } from '@/modules/Roleplay/Character/Dto/AddCustomRuleData';
import type { UpdateCustomRuleData } from '@/modules/Roleplay/Character/Dto/UpdateCustomRuleData';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import type { CharacterSessionTarget } from '@/modules/Roleplay/Character/Dto/CharacterSessionTarget';
import { characterPatchService, getCharacterSessionRuntimePort } from '@/modules/Roleplay/Character/init';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';
import {
  versions,
  fetchCharacter,
  syncCharacterVersion,
  updateCharacter as mockUpdateCharacter,
  addCustomRule as mockAddCustomRule,
  updateCustomRule as mockUpdateCustomRule,
  appendCustomRule,
  updateCustomRuleInVersion,
  getCharacterActualVersion,
} from '@/modules/Roleplay/Character/Mock/mockCharacters';

/**
 * Одиночный роутер обновлений персонажа (модель версий — Баг 1, 2026-08-20): один
 * `updateCharacter` сам решает, куда писать. Изменения во время активной сессии
 * (approved + игра playing) — в actual runtime; все остальные — в latest (`versions[id]`)
 * с автоподачей на модерацию. Сессионный слой регистрирует Game mock.
 */

/** Членство-цель активной сессии: approved + игра playing (по явному gameId или «текущей сессии»). */
export function sessionTarget(characterId: number, gameId?: number): CharacterSessionTarget | null {
  return getCharacterSessionRuntimePort()?.sessionTarget(characterId, gameId) ?? null;
}

/** После изменения latest: перепривязка кэша листа + автоподача членств. */
export function applyVersionChange(characterId: number): void {
  syncCharacterVersion(characterId);
  getCharacterSessionRuntimePort()?.syncLatestToMemberships(characterId);
}

/**
 * Обновление персонажа. `context.gameId` — из in-game редактора; без него (standalone-карточка)
 * изменение всегда идёт в latest. Возвращает деталь листа (latest) — карточка не меняется при
 * записи в actual runtime, живые правки живут в игре (approved + actual).
 */
export async function updateCharacter(
  id: number,
  data: UpdateCharacterData,
  _signal?: AbortSignal,
): Promise<CharacterDetail> {
  const runtimePort = getCharacterSessionRuntimePort();
  if (data.gameId !== undefined) {
    const target = sessionTarget(id, data.gameId);
    if (target && runtimePort) {
      const current = await actualVersionBase(target, id);
      await runtimePort.applyActualPatch(
        target.gameId,
        target.characterId,
        createRuntimePatch(id, current, data.version),
      );

      return fetchCharacter(id);
    }
  }
  if (sessionTarget(id)) {
    throw new Error('Нельзя менять лист во время сессии');
  }

  const detail = await mockUpdateCharacter(id, data);
  applyVersionChange(id);

  return detail;
}

/**
 * Выдача кастомного правила ведущим «на ходу»: во время активной сессии правило уходит в actual runtime
 * (видно в игре сразу), иначе — в latest с автоподачей.
 */
export async function addCustomRule(
  id: number,
  data: AddCustomRuleData,
  _signal?: AbortSignal,
): Promise<CharacterDetail> {
  const target = sessionTarget(id);
  const runtimePort = getCharacterSessionRuntimePort();
  if (target && runtimePort) {
    const current = await actualVersionBase(target, id);
    const next = appendCustomRule(current, data);
    await runtimePort.applyActualPatch(target.gameId, target.characterId, createRuntimePatch(id, current, next));

    return fetchCharacter(id);
  }

  const detail = await mockAddCustomRule(id, data);
  applyVersionChange(id);

  return detail;
}

/**
 * Правка/замена записи кастомного правила: во время активной сессии — в actual runtime, иначе —
 * в latest с автоподачей.
 */
export async function updateCustomRule(
  id: number,
  entryId: number,
  data: UpdateCustomRuleData,
  _signal?: AbortSignal,
): Promise<CharacterDetail> {
  const target = sessionTarget(id);
  const runtimePort = getCharacterSessionRuntimePort();
  if (target && runtimePort) {
    const current = await actualVersionBase(target, id);
    const next = await updateCustomRuleInVersion(current, entryId, data);
    await runtimePort.applyActualPatch(target.gameId, target.characterId, createRuntimePatch(id, current, next));

    return fetchCharacter(id);
  }

  const detail = await mockUpdateCustomRule(id, entryId, data);
  applyVersionChange(id);

  return detail;
}

/** База actual-листа для правок активной сессии. */
export async function actualVersionBase(
  target: CharacterSessionTarget,
  characterId: number,
): Promise<CharacterVersion> {
  const stored = getCharacterSessionRuntimePort()?.readActual(target.gameId, characterId);
  if (stored) return stored;
  const approved = target.approvedCharacterVersion ?? versions[characterId];
  if (!approved) throw new Error(`Character ${characterId} not found`);

  return structuredClone(approved);
}

function createRuntimePatch(characterId: number, before: CharacterVersion, after: CharacterVersion): CharacterPatch {
  return characterPatchService.createPatch(before, after, createRandomId(), getCharacterActualVersion(characterId));
}
