/**
 * Возраст авто-меняется только если не подходит: если ageYears не попадает ни в одну ступень
 * шкалы (сменилась раса/правила), он зажимается к минимальной границе первой ступени.
 * Возвращает значение для установки, либо null — менять не нужно (возраст подходит или не задан,
 * либо находится за верхней границей — «Старый» без ограничений).
 */
export interface AgeScaleStep {
  name: string;
  min: number;
  max: number | null;
}

export function clampAgeYears(ageYears: number | null | undefined, scale: AgeScaleStep[]): number | null {
  if (ageYears == null || scale.length === 0) return null;
  const fits = scale.some((step) => ageYears >= step.min && (step.max === null || ageYears < step.max));
  if (fits) return null;
  const first = scale[0];
  if (first && ageYears < first.min) return first.min;

  return null;
}
