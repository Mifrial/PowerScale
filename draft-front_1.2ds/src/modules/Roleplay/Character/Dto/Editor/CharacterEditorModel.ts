import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { EditorAbilityGroup } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityGroup';
import type { EditorBudgets } from '@/modules/Roleplay/Character/Dto/Editor/EditorBudgets';
import type { EditorCharacteristic } from '@/modules/Roleplay/Character/Dto/Editor/EditorCharacteristic';
import type { EditorRace } from '@/modules/Roleplay/Character/Dto/Editor/EditorRace';
import type { CharacterSenseValue } from '@/modules/Roleplay/Character/Dto/CharacterSenseValue';
import type { EditorPersonality } from '@/modules/Roleplay/Character/Dto/Editor/EditorPersonality';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';

/** View-model редактора персонажа: всё производное, посчитанное из выборов (CharacterBuild). */
export interface CharacterEditorModel {
  race: EditorRace;
  characteristics: EditorCharacteristic[];
  senses: CharacterSenseValue[];
  resources: ResourceValue[];
  abilities: EditorAbility[];
  groups: EditorAbilityGroup[];
  budgets: EditorBudgets;
  personality: EditorPersonality;
}
