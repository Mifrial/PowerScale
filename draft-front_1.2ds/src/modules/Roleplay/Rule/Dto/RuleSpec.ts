import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec'
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec'
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec'
import type { SpeciesSpec } from '@/modules/Roleplay/Rule/Dto/Race/SpeciesSpec'
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec'
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec'

/** Перечисление спеков правила по типам. simple/points/damage_type/source спеки не несут (spec отсутствует). */
export type RuleSpec = AbilitySpec | ItemSpec | RaceSpec | SpeciesSpec | ResourceSpec | CharacteristicSpec
