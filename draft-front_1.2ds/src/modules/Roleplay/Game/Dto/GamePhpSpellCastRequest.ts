import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';
import type { GamePhpSpellCastUpgrade } from '@/modules/Roleplay/Game/Dto/GamePhpSpellCastUpgrade';

/** Запрос каста: GameApi отправляет его как game.runAction kind spell. */
export interface GamePhpSpellCastRequest {
  gameId: number;
  battleId: number;
  idempotencyKey: string;
  expectedVersion: number;
  expectedSheetVersion: number;
  expectedTargetVersion?: number;
  actor: GamePhpParticipant;
  spellCode: string;
  pathCode: string;
  parameters: Record<string, DimensionalNumberValue | number>;
  usedPower: {
    source:
      | { kind: 'characteristic'; code: string }
      | { kind: 'item'; inventoryId: number }
      | { kind: 'grant'; abilityCode: string };
    value: DimensionalNumberValue;
  };
  target?: GamePhpParticipant;
  distanceIpari?: number;
  upgrades?: GamePhpSpellCastUpgrade[];
}
