import type { RULE_CONTENT_STATUSES } from '@/modules/Roleplay/Rule/Constant/RULE_CONTENT_STATUSES';

/** Цвет чипа Vuetify; актуальный — без акцента. */
export const RULE_CONTENT_STATUS_CHIP_COLORS: Partial<Record<(typeof RULE_CONTENT_STATUSES)[number], string>> = {
  broken: 'error',
  needs_work: 'warning',
};
