/** Шкала проходимости: хранится в сотых, в карточках показывается 0–100. */
export const MAP_SPEED_MAX = 10000;

/** Обычная земля без разметки. */
export const MAP_SPEED_DEFAULT = 5000;

/** Цвет области по умолчанию. */
export const MAP_AREA_DEFAULT_COLOR = '#8d8d8d';

/** Цвет новой ленты по умолчанию. */
export const MAP_LINE_DEFAULT_COLOR = '#4a7fa5';

/** Делитель для полей ввода. */
export const MAP_SPEED_INPUT_SCALE = 100;

/** Хранимая скорость → значение поля ввода. */
export function speedToInput(speed: number): number {
  return Math.round((speed / MAP_SPEED_INPUT_SCALE) * 10) / 10;
}

/** Значение поля ввода → хранимая скорость. */
export function speedFromInput(value: number): number {
  if (!Number.isFinite(value)) return MAP_SPEED_DEFAULT;

  return Math.min(MAP_SPEED_MAX, Math.max(0, Math.round(value * MAP_SPEED_INPUT_SCALE)));
}

/** Короткая подпись скорости для панели. */
export function formatSpeed(speed: number): string {
  return speed === 0 ? 'непроходимо' : String(speedToInput(speed));
}
