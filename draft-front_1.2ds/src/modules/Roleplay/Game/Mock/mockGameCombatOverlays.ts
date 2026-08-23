import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { gameCharacterMemberships } from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import { gameNpcs } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import { resourceLimitBase, statesEqual } from '@/modules/Roleplay/Game/Utils/combatEffectiveState';
import { mergeCombatOverlay } from '@/modules/Roleplay/Game/Utils/mergeCombatOverlay';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));

// Оверлеи боевых изменений per-game (ТР §8 «Боевая карточка»): персонажи — оверлей поверх
// глобального листа (до модерации), НПС — источник версия (правки пишутся сразу, без оверлея).
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
  // Эффективная база для оверлея: рабочая копия листа из редактора поверх активной версии.
  const stored = membership ? getStoredCombatOverlay(gameId, entityKey) : null;

  return stored?.sheet ?? membership?.activeVersion ?? null;
}

function emptyOverlay(gameId: number, entityKey: CombatEntityKey): GameCombatOverlay {
  return {
    gameId,
    entityKey,
    kind: entityKey.startsWith('npc:') ? 'npc' : 'character',
    resources: [],
    states: [],
    updatedAt: '',
  };
}

function ensureOverlay(
  gameId: number,
  entityKey: CombatEntityKey,
  version: CharacterVersion | null,
): GameCombatOverlay {
  const store = overlays.get(gameId) ?? new Map<CombatEntityKey, GameCombatOverlay>();
  let overlay = store.get(entityKey);
  if (!overlay) {
    overlay = {
      ...emptyOverlay(gameId, entityKey),
      states: version ? version.states.map((state) => ({ ...state })) : [],
    };
    store.set(entityKey, overlay);
    overlays.set(gameId, store);
  }

  return overlay;
}

function npcOf(gameId: number, entityKey: CombatEntityKey) {
  if (!entityKey.startsWith('npc:')) return null;

  return gameNpcs.find((npc) => npc.id === Number(entityKey.slice(4)) && npc.gameId === gameId) ?? null;
}

/** После мутации npc.version — оверлей с updatedAt, чтобы карточка боя получила новый объект версии. */
function overlayFromNpcVersion(
  gameId: number,
  entityKey: CombatEntityKey,
  version: CharacterVersion,
): GameCombatOverlay {
  const overlay = ensureOverlay(gameId, entityKey, version);
  overlay.resources = version.resources.map((item) => ({ ruleId: item.ruleId, current: { ...item.current } }));
  overlay.states = version.states.map((state) => ({ ...state }));
  overlay.updatedAt = new Date().toISOString();

  return snapshot(overlay);
}

function snapshot(overlay: GameCombatOverlay): GameCombatOverlay {
  return {
    ...overlay,
    resources: overlay.resources.map((item) => ({ ...item })),
    states: overlay.states.map((state) => ({ ...state })),
    sheet: overlay.sheet ? (JSON.parse(JSON.stringify(overlay.sheet)) as CharacterVersion) : overlay.sheet,
  };
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

/** Есть ли реальные изменения в оверлее (ресурсы, состояния или полная копия листа). */
export function combatOverlayHasChanges(version: CharacterVersion | null, overlay: GameCombatOverlay | null): boolean {
  if (!overlay || overlay.updatedAt === '') return false;
  if (overlay.sheet) return true;
  if (overlay.resources.length > 0) return true;
  if (!version) return overlay.states.length > 0;

  return !statesEqual(version.states, overlay.states);
}

/**
 * Сохранение полной рабочей копии листа из in-game редактора (модель версий — Баг 1): оверлей
 * несёт произвольные правки листа во время сессии. Боевые правки, сделанные до сохранения,
 * перекрываются содержимым sheet (ресурсы/состояния засеиваются из него).
 */
export async function writeOverlaySheet(
  gameId: number,
  entityKey: CombatEntityKey,
  sheet: CharacterVersion,
  _signal?: AbortSignal,
): Promise<GameCombatOverlay> {
  await delay(100);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');
  const overlay = ensureOverlay(gameId, entityKey, version);
  overlay.sheet = JSON.parse(JSON.stringify(sheet)) as CharacterVersion;
  overlay.resources = [];
  overlay.states = sheet.states.map((state) => ({ ...state }));
  overlay.updatedAt = new Date().toISOString();

  return snapshot(overlay);
}

/**
 * Оверлеи всех участников боя (approved-персонажи + активные НПС); без изменений — пустые записи
 * (updatedAt === ''). Эффективное состояние участника = версия листа + оверлей.
 */
export async function fetchCombatOverlays(gameId: number, _signal?: AbortSignal): Promise<GameCombatOverlay[]> {
  await delay(150);
  const keys: CombatEntityKey[] = [
    ...gameCharacterMemberships
      .filter((membership) => membership.gameId === gameId && membership.membershipStatus === 'approved')
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

/** Правка текущего значения ресурса в бою (кламп к лимиту). Персонажи — в оверлей; НПС — сразу в версию. */
export async function setCombatResource(
  gameId: number,
  entityKey: CombatEntityKey,
  ruleId: string,
  current: DimensionalNumberValue,
  _signal?: AbortSignal,
): Promise<GameCombatOverlay> {
  await delay(150);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');
  const resource = version.resources.find((item) => item.ruleId === ruleId);
  if (!resource) throw new Error('Ресурс не найден в листе участника');
  const clamped = Math.max(0, Math.min(resourceLimitBase(resource), current.base));

  const npc = npcOf(gameId, entityKey);
  if (npc) {
    if (!npc.version) throw new Error('Лист НПС не заполнен');
    npc.version.resources = npc.version.resources.map((item) =>
      item.ruleId === ruleId
        ? {
            ...item,
            current: { base: Math.max(0, Math.min(resourceLimitBase(item), current.base)), size: item.current.size },
          }
        : item,
    );
    npc.updatedAt = new Date().toISOString();

    return overlayFromNpcVersion(gameId, entityKey, npc.version);
  }

  const overlay = ensureOverlay(gameId, entityKey, version);
  const index = overlay.resources.findIndex((item) => item.ruleId === ruleId);
  const override = { ruleId, current: { base: clamped, size: resource.current.size } };
  if (index >= 0) overlay.resources[index] = override;
  else overlay.resources.push(override);
  overlay.updatedAt = new Date().toISOString();

  return snapshot(overlay);
}

/** Добавление состояния в бою. Персонажи — в оверлей; НПС — сразу в версию. */
export async function addCombatState(
  gameId: number,
  entityKey: CombatEntityKey,
  state: CharacterStateValue,
  _signal?: AbortSignal,
): Promise<GameCombatOverlay> {
  await delay(150);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');

  const npc = npcOf(gameId, entityKey);
  if (npc) {
    if (!npc.version) throw new Error('Лист НПС не заполнен');
    npc.version.states = [...npc.version.states, { ...state }];
    npc.updatedAt = new Date().toISOString();

    return overlayFromNpcVersion(gameId, entityKey, npc.version);
  }

  const overlay = ensureOverlay(gameId, entityKey, version);
  overlay.states.push({ ...state });
  overlay.updatedAt = new Date().toISOString();

  return snapshot(overlay);
}

/** Изменение значения состояния (по индексу в списке боя). Персонажи — в оверлей; НПС — сразу в версию. */
export async function setCombatStateValue(
  gameId: number,
  entityKey: CombatEntityKey,
  index: number,
  value?: number,
  _signal?: AbortSignal,
): Promise<GameCombatOverlay> {
  await delay(150);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');

  const npc = npcOf(gameId, entityKey);
  if (npc) {
    if (!npc.version) throw new Error('Лист НПС не заполнен');
    const state = npc.version.states[index];
    if (!state) throw new Error('Состояние не найдено');
    npc.version.states[index] = value === undefined ? { ...state, value: undefined } : { ...state, value };
    npc.updatedAt = new Date().toISOString();

    return overlayFromNpcVersion(gameId, entityKey, npc.version);
  }

  const overlay = ensureOverlay(gameId, entityKey, version);
  const state = overlay.states[index];
  if (!state) throw new Error('Состояние не найдено');
  overlay.states[index] = value === undefined ? { ...state, value: undefined } : { ...state, value };
  overlay.updatedAt = new Date().toISOString();

  return snapshot(overlay);
}

/** Удаление состояния (по индексу в списке боя). Персонажи — в оверлей; НПС — сразу в версию. */
export async function removeCombatState(
  gameId: number,
  entityKey: CombatEntityKey,
  index: number,
  _signal?: AbortSignal,
): Promise<GameCombatOverlay> {
  await delay(150);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');

  const npc = npcOf(gameId, entityKey);
  if (npc) {
    if (!npc.version) throw new Error('Лист НПС не заполнен');
    if (!npc.version.states[index]) throw new Error('Состояние не найдено');
    npc.version.states = npc.version.states.filter((_, i) => i !== index);
    npc.updatedAt = new Date().toISOString();

    return overlayFromNpcVersion(gameId, entityKey, npc.version);
  }

  const overlay = ensureOverlay(gameId, entityKey, version);
  if (!overlay.states[index]) throw new Error('Состояние не найдено');
  overlay.states = overlay.states.filter((_, i) => i !== index);
  overlay.updatedAt = new Date().toISOString();

  return snapshot(overlay);
}

/** Экипировка предмета в бою: персонаж — в оверлей (sheet), НПС — сразу в версию. */
export async function setCombatItemEquipped(
  gameId: number,
  entityKey: CombatEntityKey,
  itemId: number,
  equipped: boolean,
  _signal?: AbortSignal,
): Promise<GameCombatOverlay> {
  await delay(150);
  const version = entityVersion(gameId, entityKey);
  if (!version) throw new Error('Лист участника не заполнен');
  if (!version.inventory.some((item) => item.id === itemId)) throw new Error('Предмет не найден');

  const npc = npcOf(gameId, entityKey);
  if (npc) {
    if (!npc.version) throw new Error('Лист НПС не заполнен');
    npc.version.inventory = npc.version.inventory.map((item) => (item.id === itemId ? { ...item, equipped } : item));
    npc.updatedAt = new Date().toISOString();

    return overlayFromNpcVersion(gameId, entityKey, npc.version);
  }

  const overlay = ensureOverlay(gameId, entityKey, version);
  const base = overlay.sheet
    ? overlay.sheet
    : overlay.updatedAt !== ''
      ? mergeCombatOverlay(version, overlay)
      : version;
  overlay.sheet = {
    ...(JSON.parse(JSON.stringify(base)) as CharacterVersion),
    inventory: base.inventory.map((item) => (item.id === itemId ? { ...item, equipped } : item)),
  };
  overlay.updatedAt = new Date().toISOString();

  return snapshot(overlay);
}
