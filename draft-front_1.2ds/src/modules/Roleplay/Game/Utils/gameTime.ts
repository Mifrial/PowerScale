import type { GameTime } from '@/modules/Roleplay/Game/Dto/GameTime';

/**
 * Фиксированные игровые единицы времени (ТР §8 «Летопись»): 1 год = 10 месяцев · месяц =
 * 3 декады · декада = 10 дней · день = 30 часов · час = 60 минут · минута = 10 ходов ·
 * ход = 6 секунд. В летописи хранятся/отображаются единицы от года до минуты (ходы и секунды —
 * подминутная гранулярность, на уровне хроники не нужны); минута как минимальная единица
 * равна 60 секундам. Станут настройкой правил — все конверсии идут только через эти константы.
 */
export const GAME_TIME_SECONDS_PER_TURN = 6;
export const GAME_TIME_TURNS_PER_MINUTE = 10;
export const GAME_TIME_MINUTES_PER_HOUR = 60;
export const GAME_TIME_HOURS_PER_DAY = 30;
export const GAME_TIME_DAYS_PER_DECADE = 10;
export const GAME_TIME_DECADES_PER_MONTH = 3;
export const GAME_TIME_MONTHS_PER_YEAR = 10;

const SECONDS_PER_MINUTE = GAME_TIME_SECONDS_PER_TURN * GAME_TIME_TURNS_PER_MINUTE;
const SECONDS_PER_HOUR = SECONDS_PER_MINUTE * GAME_TIME_MINUTES_PER_HOUR;
const SECONDS_PER_DAY = SECONDS_PER_HOUR * GAME_TIME_HOURS_PER_DAY;
const SECONDS_PER_DECADE = SECONDS_PER_DAY * GAME_TIME_DAYS_PER_DECADE;
const SECONDS_PER_MONTH = SECONDS_PER_DECADE * GAME_TIME_DECADES_PER_MONTH;
const SECONDS_PER_YEAR = SECONDS_PER_MONTH * GAME_TIME_MONTHS_PER_YEAR;

/** Сдвиг в секундах — единственная сопоставимая мера (сортировка хроники по нему). */
export function gameTimeToSeconds(time: GameTime): number {
  return (
    time.years * SECONDS_PER_YEAR +
    time.months * SECONDS_PER_MONTH +
    time.decades * SECONDS_PER_DECADE +
    time.days * SECONDS_PER_DAY +
    time.hours * SECONDS_PER_HOUR +
    time.minutes * SECONDS_PER_MINUTE
  );
}

/**
 * Каноническая форма: максимально крупные единицы (13 месяцев → 1 год 3 месяца,
 * 61 минута → 1 час 1 минута). Неотрицательные значения.
 */
export function normalizeGameTime(time: GameTime): GameTime {
  let rest = gameTimeToSeconds(time);
  const years = Math.floor(rest / SECONDS_PER_YEAR);
  rest -= years * SECONDS_PER_YEAR;
  const months = Math.floor(rest / SECONDS_PER_MONTH);
  rest -= months * SECONDS_PER_MONTH;
  const decades = Math.floor(rest / SECONDS_PER_DECADE);
  rest -= decades * SECONDS_PER_DECADE;
  const days = Math.floor(rest / SECONDS_PER_DAY);
  rest -= days * SECONDS_PER_DAY;
  const hours = Math.floor(rest / SECONDS_PER_HOUR);
  rest -= hours * SECONDS_PER_HOUR;
  const minutes = Math.floor(rest / SECONDS_PER_MINUTE);

  return { years, months, decades, days, hours, minutes };
}

// Русские формы единственного/двойственного/множественного числа («1 год», «2 года», «5 лет»).
function plural(n: number, forms: readonly [string, string, string]): string {
  const mod10 = n % 10;
  const mod100 = n % 100;
  if (mod10 === 1 && mod100 !== 11) return forms[0];
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return forms[1];

  return forms[2];
}

const UNIT_FORMS: readonly (readonly [keyof GameTime, readonly [string, string, string]])[] = [
  ['years', ['год', 'года', 'лет']],
  ['months', ['месяц', 'месяца', 'месяцев']],
  ['decades', ['декада', 'декады', 'декад']],
  ['days', ['день', 'дня', 'дней']],
  ['hours', ['час', 'часа', 'часов']],
  ['minutes', ['минута', 'минуты', 'минут']],
];

/**
 * Человекочитаемый сдвиг каноническими единицами: «1 год и 4 дня» (нулевые единицы
 * опускаются; нулевой сдвиг — «0 минут»). Суффикс «от Начала приключения» добавляет вызывающий.
 */
export function gameTimeLabel(time: GameTime): string {
  const parts: string[] = [];
  for (const [key, forms] of UNIT_FORMS) {
    const value = time[key];
    if (value > 0) parts.push(`${value} ${plural(value, forms)}`);
  }
  if (parts.length === 0) return `0 ${plural(0, ['минута', 'минуты', 'минут'])}`;
  if (parts.length === 1) return parts[0];
  if (parts.length === 2) return `${parts[0]} и ${parts[1]}`;

  return `${parts.slice(0, -1).join(', ')} и ${parts.at(-1)}`;
}
