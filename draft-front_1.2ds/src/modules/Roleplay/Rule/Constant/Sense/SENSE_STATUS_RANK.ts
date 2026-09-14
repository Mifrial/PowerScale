import type { SenseStatus } from '@/modules/Roleplay/Rule/Enum/SenseStatus';

/** Хуже статус — больше ранг (absent побеждает precise). */
export const SENSE_STATUS_RANK: Record<SenseStatus, number> = {
  precise: 0,
  imprecise: 1,
  vague: 2,
  absent: 3,
};
