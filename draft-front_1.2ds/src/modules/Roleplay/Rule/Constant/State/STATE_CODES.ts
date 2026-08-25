/** Состояние «Увечье» на листе (проверка на увечье пишет сюда силу). */
export const MAIM_STATE_CODE = 'maim';

export const WOUND_STATE_CODE = 'wound';
export const EXHAUSTION_STATE_CODE = 'exhaustion';
export const STUNNED_STATE_CODE = 'stunned';
export const WEAKNESS_STATE_CODE = 'weakness';
export const DISABLED_STATE_CODE = 'disabled';
export const UNCONSCIOUS_STATE_CODE = 'unconscious';
export const BLOOD_LOSS_STATE_CODE = 'blood-loss';

/** Группа «Упадок сил»: одновременно не больше одного исхода. */
export const DECLINE_STATE_CODES = [WEAKNESS_STATE_CODE, DISABLED_STATE_CODE, UNCONSCIOUS_STATE_CODE] as const;
