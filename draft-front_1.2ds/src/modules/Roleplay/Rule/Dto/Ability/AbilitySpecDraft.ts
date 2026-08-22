import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import type { SpellSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SpellSpec';

/** Черновой слой редактора: type опционален, типоспецифичные поля могут «висеть» при смене типа. */
export interface AbilitySpecDraft extends AbilitySpecBase {
  type?: AbilityType;
  action_components: ActionComponent[];
  process?: ProcessSpec;
  spell?: SpellSpec;
  /** Сколько способностей можно выбрать в группе (только для type 'group'). */
  selectLimit?: number;
}
