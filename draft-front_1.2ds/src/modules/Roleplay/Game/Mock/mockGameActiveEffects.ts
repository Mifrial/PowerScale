import type { GameActiveEffect } from '@/modules/Roleplay/Game/Dto/GameActiveEffect';

/** Мок пока не ведёт длительные эффекты: серверная строка приходит из PHP. */
export async function fetchActiveEffects(_gameId: number): Promise<GameActiveEffect[]> {
  return [];
}
