import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { GameAuthoritativeCommandResult } from '@/modules/Roleplay/Game/Dto/GameAuthoritativeCommandResult';
import type { GameRuntimeMutationCommand } from '@/modules/Roleplay/Game/Dto/GameRuntimeMutationCommand';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { gameCharacterMemberships } from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import { gameNpcs } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import { characterPatchService, characterHandsService } from '@/modules/Roleplay/Character/init';
import { getCharacterActualVersion, getStoredCharacterVersion } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { mockGameRuntimeMutationService } from '@/modules/Roleplay/Game/Service/Instance/mockGameRuntimeMutationService';
import { resourceLimitBase } from '@/modules/Roleplay/Game/Utils/combatEffectiveState';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));

// Оверлеи содержат только transient game/session markers. Листы персонажей и НПС
// изменяются в actual storage и перечитываются через runtime projection.
const overlays = new Map<number, Map<CombatEntityKey, GameCombatOverlay>>();

export function combatKey(kind: 'character' | 'npc', id: number): CombatEntityKey {
  return `${kind}:${id}`;
}

function entityVersion(gameId: number, entityKey: CombatEntityKey): CharacterVersion | null {
  if (entityKey.startsWith('npc:')) {
    return gameNpcs.find((npc) => npc.id === Number(entityKey.slice(4)) && npc.gameId === gameId)?.version ?? null;
  }

  const membership = gameCharacterMemberships.find(
    (candidate) => candidate.gameId === gameId && candidate.characterId === Number(entityKey.slice(10)),
  );
  if (!membership) return null;

  return getStoredCharacterVersion(membership.characterId);
}

function emptyOverlay(gameId: number, entityKey: CombatEntityKey): GameCombatOverlay {
  return {
    gameId,
    entityKey,
    kind: entityKey.startsWith('npc:') ? 'npc' : 'character',
    updatedAt: '',
  };
}

function ensureOverlay(gameId: number, entityKey: CombatEntityKey): GameCombatOverlay {
  const store = overlays.get(gameId) ?? new Map<CombatEntityKey, GameCombatOverlay>();
  let overlay = store.get(entityKey);
  if (!overlay) {
    overlay = emptyOverlay(gameId, entityKey);
    store.set(entityKey, overlay);
    overlays.set(gameId, store);
  }

  return overlay;
}

function npcOf(gameId: number, entityKey: CombatEntityKey) {
  if (!entityKey.startsWith('npc:')) return null;

  return gameNpcs.find((npc) => npc.id === Number(entityKey.slice(4)) && npc.gameId === gameId) ?? null;
}

function snapshot(overlay: GameCombatOverlay): GameCombatOverlay {
  return cloneData(overlay);
}

async function applyVersionMutation(
  gameId: number,
  entityKey: CombatEntityKey,
  before: CharacterVersion,
  after: CharacterVersion,
): Promise<GameAuthoritativeCommandResult> {
  const commandId = createRandomId();
  const expectedActualVersion = entityKey.startsWith('npc:')
    ? (npcOf(gameId, entityKey)?.actualVersion ?? 0)
    : getCharacterActualVersion(Number(entityKey.slice(10)));
  const command: GameRuntimeMutationCommand = {
    commandId,
    gameId,
    entityKey,
    patch: characterPatchService.createPatch(before, after, commandId, expectedActualVersion),
  };

  return mockGameRuntimeMutationService.apply(command);
}

/** Applies one typed actual mutation command in the mock runtime store. */
export async function mutateRuntimeEntity(
  command: GameRuntimeMutationCommand,
  _signal?: AbortSignal,
): Promise<GameAuthoritativeCommandResult> {
  await delay(150);

  return mockGameRuntimeMutationService.apply(command);
}

/** Хранимый оверлей (null — изменений ещё не было). */
export function getStoredCombatOverlay(gameId: number, entityKey: CombatEntityKey): GameCombatOverlay | null {
  return overlays.get(gameId)?.get(entityKey) ?? null;
}

/** Копия оверлея для отдачи наружу (не мутировать хранилище). */
export function combatOverlaySnapshot(overlay: GameCombatOverlay | null): GameCombatOverlay | null {
  return overlay ? snapshot(overlay) : null;
}

/** Очистка оверлея (после approve/reject модерации). */
export function clearCombatOverlay(gameId: number, entityKey: CombatEntityKey): void {
  overlays.get(gameId)?.delete(entityKey);
}

/** Снимок map оверлеев игры (включая NPC) для атомарного stop. */
export function snapshotCombatOverlayStore(gameId: number): Record<CombatEntityKey, GameCombatOverlay> {
  const store = overlays.get(gameId);
  if (!store) return {};

  return Object.fromEntries([...store.entries()].map(([key, overlay]) => [key, cloneData(overlay)]));
}

/** Полная замена map оверлеев игры. Пустой snapshot удаляет все ключи. */
export function restoreCombatOverlayStore(gameId: number, snapshot: Record<CombatEntityKey, GameCombatOverlay>): void {
  const next = new Map<CombatEntityKey, GameCombatOverlay>();
  for (const [key, overlay] of Object.entries(snapshot)) {
    next.set(key as CombatEntityKey, cloneData(overlay));
  }
  overlays.set(gameId, next);
}

/** Actual mutations do not require a stop-time overlay commit. */
export function combatOverlayHasChanges(_version: CharacterVersion | null, overlay: GameCombatOverlay | null): boolean {
  return Boolean(overlay && overlay.updatedAt !== '');
}

/** Transient marker snapshots for approved participants and active NPCs. */
export async function fetchCombatOverlays(gameId: number, _signal?: AbortSignal): Promise<GameCombatOverlay[]> {
  await delay(150);
  const keys: CombatEntityKey[] = [
    ...gameCharacterMemberships
      .filter((membership) => membership.gameId === gameId && membership.membershipStatus === 'active')
      .map((membership) => combatKey('character', membership.characterId)),
    ...gameNpcs
      .filter((npc) => npc.gameId === gameId && npc.status === 'active')
      .map((npc) => combatKey('npc', npc.id)),
  ];
  const store = overlays.get(gameId);

  return keys.map((key) => {
    const overlay = store?.get(key);

    return overlay ? snapshot(overlay) : emptyOverlay(gameId, key);
  });
}

/** Правка текущего значения ресурса в actual-листе. */
export async function setCombatResource(
  gameId: number,
  entityKey: CombatEntityKey,
  ruleCode: string,
  current: DimensionalNumberValue,
  _signal?: AbortSignal,
): Promise<GameAuthoritativeCommandResult> {
  await delay(150);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');
  const resource = version.resources.find((item) => item.ruleCode === ruleCode);
  if (!resource) throw new Error('Ресурс не найден в листе участника');
  const clamped = Math.max(0, Math.min(resourceLimitBase(resource), current.base));

  return applyVersionMutation(gameId, entityKey, version, {
    ...version,
    resources: version.resources.map((item) =>
      item.ruleCode === ruleCode ? { ...item, current: { base: clamped, size: item.current.size } } : item,
    ),
  });
}

/** Флаг цикла жетонов концентрации (тратили ли с конца предыдущего своего хода). */
export async function setCombatConcentrationUsedInCycle(
  gameId: number,
  entityKey: CombatEntityKey,
  used: boolean,
  _signal?: AbortSignal,
): Promise<GameCombatOverlay> {
  await delay(50);
  if (!entityVersion(gameId, entityKey)) throw new Error('Лист участника не заполнен');
  const overlay = ensureOverlay(gameId, entityKey);
  overlay.concentrationUsedInCycle = used;
  overlay.updatedAt = new Date().toISOString();

  return snapshot(overlay);
}

export async function setCombatWoundBandagedOnce(
  gameId: number,
  entityKey: CombatEntityKey,
  bandaged: boolean,
  _signal?: AbortSignal,
): Promise<GameCombatOverlay> {
  await delay(80);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');
  const overlay = ensureOverlay(gameId, entityKey);
  overlay.woundBandagedOnce = bandaged;
  overlay.updatedAt = new Date().toISOString();

  return snapshot(overlay);
}

/** Добавление состояния в actual-лист. */
export async function addCombatState(
  gameId: number,
  entityKey: CombatEntityKey,
  state: CharacterStateValue,
  _signal?: AbortSignal,
): Promise<GameAuthoritativeCommandResult> {
  await delay(150);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');

  return applyVersionMutation(gameId, entityKey, version, {
    ...version,
    states: [...version.states, { ...state }],
  });
}

/** Полная замена записи состояния (счётчик DOT, сила яда). */
export async function replaceCombatState(
  gameId: number,
  entityKey: CombatEntityKey,
  index: number,
  state: CharacterStateValue,
  _signal?: AbortSignal,
): Promise<GameAuthoritativeCommandResult> {
  await delay(150);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');

  if (!version.states[index]) throw new Error('Состояние не найдено');

  return applyVersionMutation(gameId, entityKey, version, {
    ...version,
    states: version.states.map((item, stateIndex) => (stateIndex === index ? { ...state } : item)),
  });
}

/** Изменение значения состояния (по индексу в списке боя). */
export async function setCombatStateValue(
  gameId: number,
  entityKey: CombatEntityKey,
  index: number,
  value?: number,
  _signal?: AbortSignal,
): Promise<GameAuthoritativeCommandResult> {
  await delay(150);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');

  const state = version.states[index];
  if (!state) throw new Error('Состояние не найдено');

  return applyVersionMutation(gameId, entityKey, version, {
    ...version,
    states: version.states.map((item, stateIndex) =>
      stateIndex === index ? (value === undefined ? { ...item, value: undefined } : { ...item, value }) : item,
    ),
  });
}

/** Удаление состояния (по индексу в списке боя). */
export async function removeCombatState(
  gameId: number,
  entityKey: CombatEntityKey,
  index: number,
  _signal?: AbortSignal,
): Promise<GameAuthoritativeCommandResult> {
  await delay(150);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');

  if (!version.states[index]) throw new Error('Состояние не найдено');

  return applyVersionMutation(gameId, entityKey, version, {
    ...version,
    states: version.states.filter((_, stateIndex) => stateIndex !== index),
  });
}

/** Экипировка предмета в бою в actual-листе. */
export async function setCombatItemEquipped(
  gameId: number,
  entityKey: CombatEntityKey,
  itemId: number,
  equipped: boolean,
  _signal?: AbortSignal,
): Promise<GameAuthoritativeCommandResult> {
  await delay(150);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');
  if (!version.inventory.some((item) => item.id === itemId)) throw new Error('Предмет не найден');

  return applyVersionMutation(gameId, entityKey, version, {
    ...version,
    inventory: version.inventory.map((item) => (item.id === itemId ? { ...item, equipped } : item)),
  });
}

/** Занятость слотов рук предмета в actual-листе. */
export async function setCombatItemOccupyHands(
  gameId: number,
  entityKey: CombatEntityKey,
  itemId: number,
  occupyHands: number,
  _signal?: AbortSignal,
): Promise<GameAuthoritativeCommandResult> {
  await delay(150);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');
  if (!version.inventory.some((item) => item.id === itemId)) throw new Error('Предмет не найден');
  const inventory = characterHandsService.withOccupyHands(version.inventory, itemId, occupyHands, ruleCatalog);

  return applyVersionMutation(gameId, entityKey, version, {
    ...version,
    inventory,
  });
}
