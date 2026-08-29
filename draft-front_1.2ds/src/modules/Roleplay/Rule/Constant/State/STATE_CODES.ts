/** Состояние «Увечье» на листе (проверка на увечье пишет сюда силу). */
export const MAIM_STATE_CODE = 'maim';

export const WOUND_STATE_CODE = 'wound';
export const EXHAUSTION_STATE_CODE = 'exhaustion';
export const ACCUMULATED_DAMAGE_STATE_CODE = 'accumulated-damage';
export const STUNNED_STATE_CODE = 'stunned';
export const WEAKNESS_STATE_CODE = 'weakness';
export const DISABLED_STATE_CODE = 'disabled';
export const UNCONSCIOUS_STATE_CODE = 'unconscious';
export const BLOOD_LOSS_STATE_CODE = 'blood-loss';
export const BURNING_STATE_CODE = 'burning';
export const POISONING_STATE_CODE = 'poisoning';
export const ATTRACTIVENESS_STATE_CODE = 'attractiveness';
export const ATTRACTIVENESS_MIN = -3;
export const ATTRACTIVENESS_MAX = 3;

/** Группа «Упадок сил»: одновременно не больше одного исхода. */
export const DECLINE_STATE_CODES = [WEAKNESS_STATE_CODE, DISABLED_STATE_CODE, UNCONSCIOUS_STATE_CODE] as const;
