import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { UpdateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/UpdateCharacterData';
import type { AddCustomRuleData } from '@/modules/Roleplay/Character/Dto/AddCustomRuleData';
import type { UpdateCustomRuleData } from '@/modules/Roleplay/Character/Dto/UpdateCustomRuleData';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterSessionTarget } from '@/modules/Roleplay/Character/Dto/CharacterSessionTarget';
import { getCharacterSessionOverlay } from '@/modules/Roleplay/Character/init';
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

/**
 * Одиночный роутер обновлений персонажа (модель версий — Баг 1, 2026-08-20): один
 * `updateCharacter` сам решает, куда писать. Изменения во время активной сессии
 * (approved + игра playing) — в сессионный оверлей; все остальные — в latest (`versions[id]`)
 * с автоподачей на модерацию. Сессионный слой регистрирует Game mock.
 */

/** Членство-цель активной сессии: approved + игра playing (по явному gameId или «текущей сессии»). */
export function sessionTarget(characterId: number, gameId?: number): CharacterSessionTarget | null {
  return getCharacterSessionOverlay()?.sessionTarget(characterId, gameId) ?? null;
}

/** После изменения latest: перепривязка кэша листа + автоподача членств. */
export function applyVersionChange(characterId: number): void {
  syncCharacterVersion(characterId);
  getCharacterSessionOverlay()?.syncLatestToMemberships(characterId);
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
  const overlay = getCharacterSessionOverlay();
  if (data.gameId !== undefined) {
    const target = sessionTarget(id, data.gameId);
    if (target && overlay) {
      await overlay.writeSheet(target.gameId, target.characterId, data.version);

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
 * Выдача кастомного правила ведущим «на ходу»: во время активной сессии правило уходит в оверлей
 * (видно в игре сразу), иначе — в latest с автоподачей.
 */
export async function addCustomRule(
  id: number,
  data: AddCustomRuleData,
  _signal?: AbortSignal,
): Promise<CharacterDetail> {
  const target = sessionTarget(id);
  const overlay = getCharacterSessionOverlay();
  if (target && overlay) {
    const version = await overlaySheetBase(target, id);
    await overlay.writeSheet(target.gameId, target.characterId, appendCustomRule(version, data));

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
  const overlay = getCharacterSessionOverlay();
  if (target && overlay) {
    const version = await overlaySheetBase(target, id);
    await overlay.writeSheet(
      target.gameId,
      target.characterId,
      await updateCustomRuleInVersion(version, entryId, data),
    );

    return fetchCharacter(id);
  }

  const detail = await mockUpdateCustomRule(id, entryId, data);
  applyVersionChange(id);

  return detail;
}

/** База оверлейного листа для правок: существующий sheet или копия активной версии. */
export async function overlaySheetBase(target: CharacterSessionTarget, characterId: number): Promise<CharacterVersion> {
  const stored = getCharacterSessionOverlay()?.readSheet(target.gameId, characterId);
  if (stored) return stored;
  const approved = target.approvedCharacterVersion ?? versions[characterId];
  if (!approved) throw new Error(`Character ${characterId} not found`);

  return JSON.parse(JSON.stringify(approved)) as CharacterVersion;
}
