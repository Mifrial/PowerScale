import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';

/** Признак «Атака» (keyword attack): атакующие способности каталога. */
export const ATTACK_KEYWORD_ID = 71;
/** Признак раздела «Тело» (keyword section-body): физическое развитие. */
export const SECTION_BODY_KEYWORD_ID = 61;

export function isAttackAbility(ability: Pick<EditorAbility, 'keywordIds'>): boolean {
  return ability.keywordIds.includes(ATTACK_KEYWORD_ID);
}

export function isPhysicalDevelopmentAbility(ability: Pick<EditorAbility, 'keywordIds'>): boolean {
  return ability.keywordIds.includes(SECTION_BODY_KEYWORD_ID);
}

/** «Взятое»: куплено за ОР, получено даром-навыком особенности или дано автоматически. */
export function isAcquiredAbility(ability: Pick<EditorAbility, 'level' | 'gifted' | 'automatic'>): boolean {
  return ability.level > 0 || ability.gifted || ability.automatic;
}
