import type { GameLootDistribution } from '@/modules/Roleplay/Game/Dto/GameLoot';

/** Фиксация раздачи добычи ведущим: итоговые доли (для денег — суммы в гм). */
export interface DistributeLootData {
  distribution: GameLootDistribution[];
}
