import type { GameLootDistribution } from '@/modules/Roleplay/Game/Dto/GameLoot';

/** Idempotent actual mutation command for loot distribution. */
export interface DistributeLootData {
  commandId: string;
  expectedActualVersions: Record<string, number>;
  distribution: GameLootDistribution[];
}
