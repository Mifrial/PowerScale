import type { SenseStatus } from '@/modules/Roleplay/Rule/Enum/SenseStatus';

/** Подписи статуса чувства для редактора гранта/спеки. */
export const SENSE_STATUS_OPTIONS: { title: string; value: SenseStatus }[] = [
  { title: 'Точное', value: 'precise' },
  { title: 'Неточное', value: 'imprecise' },
  { title: 'Смутное', value: 'vague' },
  { title: 'Отсутствует', value: 'absent' },
];
