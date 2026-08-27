/** Корень обычных проверок. */
export const CHECK_SIMPLE_CODE = 'check-simple';

export const CHECK_HIT_CODE = 'check-hit';
export const CHECK_EXHAUSTION_CODE = 'check-exhaustion';
export const CHECK_INITIATIVE_CODE = 'check-initiative';
export const CHECK_INJURY_CODE = 'check-injury';
export const CHECK_COMMUNICATION_CODE = 'check-communication';

/** Правила простой проверки (коды карточек, не механик); наследуются потомкам. */
export const CHECK_SIMPLE_ATTACHED_RULE_CODES = ['rule-6-and-1', 'advantages'];

/** Проверка на увечье: те же правила броска, что у простой (6 и 1, преимущества). */
export const CHECK_INJURY_ATTACHED_RULE_CODES = [...CHECK_SIMPLE_ATTACHED_RULE_CODES];

/** domain_ref навыка «Манера общения» — дети CHECK_COMMUNICATION_CODE. */
export const COMMUNICATION_CHECK_DOMAIN_REF = 'communication-check';

/** Проверка на музицирование голосом — правило проверки ещё не в каталоге. */
export const CHECK_VOICE_MUSIC_CODE = 'voice-music';
