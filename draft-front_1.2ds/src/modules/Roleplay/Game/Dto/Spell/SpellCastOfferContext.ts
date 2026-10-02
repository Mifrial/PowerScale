import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Контекст сотворения на оферте касания: каст и эффект после удара. */
export interface SpellCastOfferContext {
  spellCode: string;
  usedPower: DimensionalNumberValue;
  availableControl: DimensionalNumberValue;
  parameterValues: Record<string, DimensionalNumberValue>;
  hasSpellTarget: boolean;
  spellTargetKey: CombatEntityKey | null;
  targetResistanceAmount: number;
  parameterPower: DimensionalNumberValue;
  checkCode: string | null;
  castCheckCode: string | null;
  characteristicValue: DimensionalNumberValue;
  characteristicName: string;
  touchActionCode: string | null;
  touchActionName: string;
  spellOd: number;
  touchOd: number;
  spentAp: number;
  /** Preview-модификатор сложности, зарезервированный для этого каста. */
  trainingDifficultyDelta?: number;
  /** Подпись pending-состояния на момент создания offer. */
  pendingSignature?: string | null;
  sourceKey?: string;
  pathCode?: string | null;
  appliedUpgradeCodes?: string[];
  chargeSustainId?: string;
}
