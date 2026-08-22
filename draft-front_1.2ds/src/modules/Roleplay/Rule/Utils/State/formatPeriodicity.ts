import type { PeriodStep } from '@/modules/Roleplay/Rule/Dto/State/Periodicity';

const STEP_ONE: Record<PeriodStep, string> = {
  turn: 'каждый ход',
  minute: 'каждую минуту',
  hour: 'каждый час',
  day: 'каждый день',
  month: 'каждый месяц',
  year: 'каждый год',
};

const STEP_PLURAL: Record<PeriodStep, [string, string, string]> = {
  turn: ['ход', 'хода', 'ходов'],
  minute: ['минуту', 'минуты', 'минут'],
  hour: ['час', 'часа', 'часов'],
  day: ['день', 'дня', 'дней'],
  month: ['месяц', 'месяца', 'месяцев'],
  year: ['год', 'года', 'лет'],
};

function plural(n: number, forms: [string, string, string]): string {
  const abs = Math.abs(n);
  const mod10 = abs % 10;
  const mod100 = abs % 100;
  if (mod10 === 1 && mod100 !== 11) return forms[0];
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return forms[1];

  return forms[2];
}

export function formatPeriodicity(value: number, step: PeriodStep): string {
  if (value === 1) return STEP_ONE[step];

  return `каждые ${value} ${plural(value, STEP_PLURAL[step])}`;
}
