import type { SpellBurstTarget } from '@/modules/Roleplay/Game/Dto/Spell/SpellBurstTarget';

/** Цель взрыва в запросе `game.runAction` фазы resolve. */
export interface SpellBurstTargetRequest {
  type: 'character' | 'npc';
  id: number;
  distanceIpari: number;
}

/** Переводит ключи боя в плоские стороны запроса; мусорные ключи отбрасывает. */
export function toBurstTargetRequest(targets: SpellBurstTarget[]): SpellBurstTargetRequest[] {
  const rows: SpellBurstTargetRequest[] = [];
  for (const target of targets) {
    const [rawType, rawId] = String(target.key).split(':');
    const id = Number(rawId);
    if ((rawType !== 'character' && rawType !== 'npc') || !Number.isInteger(id) || id < 1) {
      continue;
    }
    rows.push({ type: rawType, id, distanceIpari: Math.max(0, Math.trunc(target.distanceIpari)) });
  }

  return rows;
}
