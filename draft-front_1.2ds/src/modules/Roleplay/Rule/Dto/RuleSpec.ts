import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec';
import type { SpeciesSpec } from '@/modules/Roleplay/Rule/Dto/Race/SpeciesSpec';
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import type { PoisonSpec } from '@/modules/Roleplay/Rule/Dto/Poison/PoisonSpec';
import type { SenseSpec } from '@/modules/Roleplay/Rule/Dto/SenseSpec';
import type { AgeSpec } from '@/modules/Roleplay/Rule/Dto/Age/AgeSpec';
import type { LanguageSpec } from '@/modules/Roleplay/Rule/Dto/LanguageSpec';
import type { WeaponFamilySpec } from '@/modules/Roleplay/Rule/Dto/Item/WeaponFamilySpec';
import type { ItemModifierSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';
import type { ItemModifierTypeSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierTypeSpec';
import type { CheckSpec } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import type { DamageTypeSpec } from '@/modules/Roleplay/Rule/Dto/Damage/DamageTypeSpec';

/** Перечисление спеков правила по типам. simple/points/source спеки не несут (spec отсутствует). */
export type RuleSpec =
  | AbilitySpec
  | ItemSpec
  | RaceSpec
  | SpeciesSpec
  | ResourceSpec
  | CharacteristicSpec
  | StateSpec
  | PoisonSpec
  | SenseSpec
  | AgeSpec
  | LanguageSpec
  | WeaponFamilySpec
  | ItemModifierSpec
  | ItemModifierTypeSpec
  | CheckSpec
  | DamageTypeSpec;
