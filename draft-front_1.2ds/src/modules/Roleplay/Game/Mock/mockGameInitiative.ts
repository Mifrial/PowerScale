import type { GameInitiative } from '@/modules/Roleplay/Game/Dto/GameInitiative';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));

// Шкала инициативы per-game (ТР §8 «Чат игры»). Участники хранятся в порядке хода
// (результат броска не хранится); добавленные в бой — в конце. `active` — шкала открыта.
const initiatives = new Map<number, GameInitiative>();

function emptyInitiative(gameId: number): GameInitiative {
  return { gameId, active: false, participants: [], activeIndex: null, round: 1, updatedAt: '' };
}

function validate(data: GameInitiative): void {
  if (data.activeIndex !== null && (data.activeIndex < 0 || data.activeIndex >= data.participants.length)) {
    throw new Error('Индекс текущего хода вне диапазона участников');
  }
  if (typeof data.round !== 'number' || data.round < 1) {
    throw new Error('Номер раунда должен быть >= 1');
  }
  for (const participant of data.participants) {
    if (!participant.id || !participant.name) throw new Error('Участник инициативы должен иметь id и имя');
    if (participant.kind !== 'character' && participant.kind !== 'npc') {
      throw new Error('Неизвестный тип участника инициативы');
    }
  }
}

export async function fetchInitiative(gameId: number, _signal?: AbortSignal): Promise<GameInitiative> {
  await delay(150);
  const existing = initiatives.get(gameId);
  if (!existing) return emptyInitiative(gameId);

  return { ...existing, participants: existing.participants.map((p) => ({ ...p })) };
}

export async function saveInitiative(
  gameId: number,
  data: GameInitiative,
  _signal?: AbortSignal,
): Promise<GameInitiative> {
  await delay(200);
  validate(data);
  const stored: GameInitiative = {
    gameId,
    active: data.active,
    participants: data.participants.map((p) => ({ ...p })),
    activeIndex: data.activeIndex,
    round: data.round,
    updatedAt: new Date().toISOString(),
  };
  initiatives.set(gameId, stored);

  return { ...stored, participants: stored.participants.map((p) => ({ ...p })) };
}
