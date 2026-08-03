import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { ActionCost } from '@/modules/Roleplay/Rule/Dto/Ability/ActionCost';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import type { SpellSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SpellSpec';

/** Черновой слой редактора: type опционален, типоспецифичные поля могут «висеть» при смене типа. */
export interface AbilitySpecDraft extends AbilitySpecBase {
  type?: AbilityType;
  action_costs: ActionCost[];
  process?: ProcessSpec;
  spell?: SpellSpec;
}
