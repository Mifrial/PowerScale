import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));

// Быстрые броски (макросы) per-game: entityKey → ruleCode характеристик (порядок добавления).
// Прототип на моке (ТР §8 «Боевая карточка»); модель допускает перенос на бэк.
const quickRolls = new Map<number, Map<CombatEntityKey, string[]>>();

function storeOf(gameId: number): Map<CombatEntityKey, string[]> {
  let store = quickRolls.get(gameId);
  if (!store) {
    store = new Map();
    quickRolls.set(gameId, store);
  }

  return store;
}

/** Быстрые броски игры: ключ сущности → ruleCode характеристик. */
export async function fetchQuickRolls(
  gameId: number,
  _signal?: AbortSignal,
): Promise<Record<CombatEntityKey, string[]>> {
  await delay(100);
  const result: Record<CombatEntityKey, string[]> = {};
  for (const [key, list] of storeOf(gameId)) result[key] = [...list];

  return result;
}

/** Добавить быстрый бросок (ruleCode характеристики сущности); дубликаты игнорируются. */
export async function addQuickRoll(
  gameId: number,
  entityKey: CombatEntityKey,
  ruleCode: string,
  _signal?: AbortSignal,
): Promise<string[]> {
  await delay(100);
  const store = storeOf(gameId);
  const list = store.get(entityKey) ?? [];
  if (!list.includes(ruleCode)) list.push(ruleCode);
  store.set(entityKey, list);

  return [...list];
}

/** Убрать быстрый бросок. */
export async function removeQuickRoll(
  gameId: number,
  entityKey: CombatEntityKey,
  ruleCode: string,
  _signal?: AbortSignal,
): Promise<string[]> {
  await delay(100);
  const store = storeOf(gameId);
  const list = (store.get(entityKey) ?? []).filter((id) => id !== ruleCode);
  store.set(entityKey, list);

  return [...list];
}
