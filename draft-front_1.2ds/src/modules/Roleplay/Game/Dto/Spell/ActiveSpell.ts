import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Висящее поддерживаемое заклинание (сосед ProcessSession, не state листа). */
export interface ActiveSpell {
  id: string;
  gameId: number;
  casterKey: CombatEntityKey;
  spellCode: string;
  sourceKey: string;
  pathCode: string | null;
  durationType: 'sustained';
  usedPower: DimensionalNumberValue;
  sustainPower: DimensionalNumberValue;
  stability: DimensionalNumberValue;
  parameterValues: Record<string, DimensionalNumberValue>;
  appliedUpgradeCodes: string[];
  startedRound: number;
  startedParticipantId: string;
}
