import type { AbilitySpecDraft } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecDraft';
import { ABILITY_SPEC_FIELDS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_SPEC_FIELDS';

/** Универсум типоспецифичных полей способности — производная от манифеста ABILITY_SPEC_FIELDS (union значений).
 * Используется фабрикой в prune: поле чистится, если не разрешено текущему типу. */
export const ABILITY_TYPE_SPECIFIC_FIELDS: (keyof AbilitySpecDraft)[] = [
  ...new Set(Object.values(ABILITY_SPEC_FIELDS).flat()),
];
