/** Округляет число и зажимает в диапазон; нечисло — запасное значение. */
export function clampInt(value: number, min: number, max: number, fallback: number): number {
  const number = Math.round(Number(value));
  if (!Number.isFinite(number)) return fallback;

  return Math.min(max, Math.max(min, number));
}
