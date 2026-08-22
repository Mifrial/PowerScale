import type { PeriodStep } from '@/modules/Roleplay/Rule/Dto/State/Periodicity';

export const PERIOD_STEPS: { title: string; value: PeriodStep }[] = [
  { title: 'ход', value: 'turn' },
  { title: 'минута', value: 'minute' },
  { title: 'час', value: 'hour' },
  { title: 'день', value: 'day' },
  { title: 'месяц', value: 'month' },
  { title: 'год', value: 'year' },
];
