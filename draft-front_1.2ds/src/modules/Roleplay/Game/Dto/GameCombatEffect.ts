import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

interface GameCombatEffectBase {
  effectId: string;
  entityKey: CombatEntityKey;
}

interface GameCombatResourceEffect extends GameCombatEffectBase {
  kind: 'resourceSpend';
  resourceRuleCode: string;
  amount: DimensionalNumberValue;
  remaining: DimensionalNumberValue;
}

interface GameCombatDamageEffect extends GameCombatEffectBase {
  kind: 'damage';
  damage: DimensionalNumberValue;
  damageTypeCode: string | null;
}

interface GameCombatStateEffect extends GameCombatEffectBase {
  kind: 'state';
  operation: 'add' | 'replace' | 'remove';
  stateRuleCode: string;
  state: CharacterStateValue | null;
}

export type GameCombatEffect = GameCombatResourceEffect | GameCombatDamageEffect | GameCombatStateEffect;
