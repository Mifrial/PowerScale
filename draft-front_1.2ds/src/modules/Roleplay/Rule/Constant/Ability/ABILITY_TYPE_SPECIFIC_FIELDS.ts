import type { AbilitySpecDraft } from '../../Dto/Ability/AbilitySpecDraft'
import { ABILITY_SPEC_FIELDS } from './ABILITY_SPEC_FIELDS'

/** Универсум типоспецифичных полей способности — производная от манифеста ABILITY_SPEC_FIELDS (union значений).
 * Используется фабрикой в prune: поле чистится, если не разрешено текущему типу. */
export const ABILITY_TYPE_SPECIFIC_FIELDS: (keyof AbilitySpecDraft)[] = [
  ...new Set(Object.values(ABILITY_SPEC_FIELDS).flat()),
]
