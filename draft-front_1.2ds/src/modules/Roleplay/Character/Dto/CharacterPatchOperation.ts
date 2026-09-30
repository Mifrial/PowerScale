import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterPointsState } from '@/modules/Roleplay/Character/Dto/CharacterPointsState';
import type { CharacterSenseValue } from '@/modules/Roleplay/Character/Dto/CharacterSenseValue';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacteristicValue } from '@/modules/Roleplay/Character/Dto/CharacteristicValue';
import type { CustomRuleEntry } from '@/modules/Roleplay/Character/Dto/CustomRuleEntry';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

type CharacterScalarPatchOperation =
  | { kind: 'setField'; field: 'name'; value: string }
  | { kind: 'setField'; field: 'shortDescription' | 'fullDescription'; value: string | null }
  | { kind: 'setField'; field: 'raceRuleCode'; value: string | null }
  | { kind: 'setField'; field: 'money'; value: number }
  | { kind: 'setField'; field: 'ageYears'; value: number | null }
  | {
      kind: 'setField';
      field: 'ethnicityCode' | 'ethnicityText' | 'nativeLanguageCode' | 'nativeLanguageText';
      value: string | null;
    };

type CharacterSectionPatchOperation =
  | { kind: 'replaceSection'; section: 'characteristics'; value: CharacteristicValue[] }
  | { kind: 'replaceSection'; section: 'resources'; value: ResourceValue[] }
  | { kind: 'replaceSection'; section: 'abilities'; value: CharacterAbility[] }
  | { kind: 'replaceSection'; section: 'points'; value: CharacterPointsState }
  | { kind: 'replaceSection'; section: 'inventory'; value: InventoryItem[] }
  | { kind: 'replaceSection'; section: 'states'; value: CharacterStateValue[] }
  | { kind: 'replaceSection'; section: 'senses'; value: CharacterSenseValue[] }
  | { kind: 'replaceSection'; section: 'customRules'; value: CustomRuleEntry[] };

type CharacterRuntimePatchOperation =
  | { kind: 'setResourceCurrent'; ruleCode: string; current: DimensionalNumberValue }
  | { kind: 'setInventoryQuantity'; itemId: number; quantity: number }
  | { kind: 'setInventoryEquipped'; itemId: number; equipped: boolean };

/** Типизированная операция изменения candidate actual без произвольного JSON path. */
export type CharacterPatchOperation =
  CharacterScalarPatchOperation | CharacterSectionPatchOperation | CharacterRuntimePatchOperation;
