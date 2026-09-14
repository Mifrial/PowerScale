import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { SpellCastResolveInput } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastResolveInput';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';

export interface SpellCastExecutionInput {
  spellCode: string;
  casterKey: CombatEntityKey;
  casterOverview: CharacterOverview;
  casterAbilities: CharacterAbility[];
  currentActionPoints: DimensionalNumberValue;
  resolve: SpellCastResolveInput;
  checkCode: string | null;
  characteristicValue: DimensionalNumberValue;
  characteristicName: string;
  parameterPower: DimensionalNumberValue;
  keywords: Keyword[];
  rules: Rule[];
  mechanics: Mechanic[];
  rng: DiceRng;
  touchActionCode: string | null;
  touchProfile: Pick<
    AttackOverview,
    'itemName' | 'profileType' | 'accuracy' | 'reach' | 'falloff' | 'damage' | 'damageTypeCode'
  > | null;
  touchTargetKey: CombatEntityKey | null;
  touchTargetOverview: CharacterOverview | null;
  effectTargetOverview: CharacterOverview | null;
  distanceIpari: number;
  sourceKey: string;
  pathCode: string | null;
  appliedUpgradeCodes: string[];
  extraCheckAdvantages?: AdvantageModifier[];
  chargeSpendCost?: number;
}
