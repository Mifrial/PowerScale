import type { GameManualSheetRequest } from '@/modules/Roleplay/Game/Dto/GameManualSheetRequest';
import type { GameManualSheetResult } from '@/modules/Roleplay/Game/Dto/GameManualSheetResult';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { getChatApi } from '@/modules/Messages/Chat/init';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import {
  addCombatState,
  combatKey,
  removeCombatState,
  replaceCombatState,
  setCombatResource,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import { gameCharacterMemberships } from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import { gameNpcs } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import { getStoredCharacterVersion } from '@/modules/Roleplay/Character/Mock/mockCharacters';

function entityKeyOf(target: GameManualSheetRequest['target']): CombatEntityKey {
  return combatKey(target.kind, target.id);
}

function versionOf(gameId: number, key: CombatEntityKey) {
  if (key.startsWith('npc:')) {
    return gameNpcs.find((npc) => npc.id === Number(key.slice(4)) && npc.gameId === gameId)?.version ?? null;
  }

  const membership = gameCharacterMemberships.find(
    (candidate) => candidate.gameId === gameId && candidate.characterId === Number(key.slice(10)),
  );

  return membership ? getStoredCharacterVersion(membership.characterId) : null;
}

async function say(gameId: number, text: string): Promise<void> {
  const chatId = gameDetails.find((detail) => detail.game.id === gameId)?.gameChatId;
  if (chatId === null || chatId === undefined) return;
  try {
    await getChatApi().sendMessage(chatId, text, []);
  } catch {
    // Нет членства в чате фикстуры — actual всё равно пишется.
  }
}

function nextId(states: CharacterStateValue[]): number {
  return states.reduce((max, row) => Math.max(max, row.id ?? 0), 0) + 1;
}

function currentOf(
  details: Record<string, unknown>,
): DimensionalNumberValue {
  const current = details.current;
  if (typeof current === 'number') return { base: current, size: 0 };
  if (current && typeof current === 'object' && 'base' in current) {
    const record = current as { base: number; size?: number };

    return { base: record.base, size: record.size ?? 0 };
  }

  return { base: 0, size: 0 };
}

/** Ручная правка мока: actual и строка чата. */
export async function manualSheet(
  body: GameManualSheetRequest,
  _signal?: AbortSignal,
): Promise<GameManualSheetResult> {
  const key = entityKeyOf(body.target);
  const before = versionOf(body.gameId, key);
  if (!before) throw new Error('Лист участника не заполнен');
  if (body.operation === 'setResource') {
    const code = String(body.details.resourceCode ?? '');
    const result = await setCombatResource(body.gameId, key, code, currentOf(body.details));
    await say(body.gameId, `Задаёт ${code}.`);

    return { actualVersion: result.actualVersion };
  }
  if (body.operation === 'addState') {
    const state = {
      stateRuleCode: String(body.details.stateRuleCode ?? ''),
      id: nextId(before.states),
      ...(typeof body.details.value === 'number' ? { value: body.details.value } : {}),
      ...(body.details.value && typeof body.details.value === 'object'
        ? { dimensionalValue: body.details.value as DimensionalNumberValue }
        : {}),
      ...(body.details.maim ? { maim: body.details.maim as CharacterStateValue['maim'] } : {}),
    } as CharacterStateValue;
    const result = await addCombatState(body.gameId, key, state);
    await say(body.gameId, `Добавляет состояние «${state.stateRuleCode}».`);

    return { actualVersion: result.actualVersion };
  }
  const id = body.details.id;
  if (typeof id !== 'number') throw new Error('Нет id состояния');
  const index = before.states.findIndex((row) => row.id === id);
  if (index < 0) throw new Error('Состояние не найдено');
  if (body.operation === 'removeState') {
    const result = await removeCombatState(body.gameId, key, index);
    await say(body.gameId, 'Снимает состояние.');

    return { actualVersion: result.actualVersion };
  }
  const prev = before.states[index];
  const patched: CharacterStateValue = {
    ...prev,
    ...(typeof body.details.value === 'number' ? { value: body.details.value } : {}),
    ...(body.details.maim ? { maim: body.details.maim as CharacterStateValue['maim'] } : {}),
  };
  const result = await replaceCombatState(body.gameId, key, index, patched);
  await say(body.gameId, 'Меняет состояние.');

  return { actualVersion: result.actualVersion };
}
