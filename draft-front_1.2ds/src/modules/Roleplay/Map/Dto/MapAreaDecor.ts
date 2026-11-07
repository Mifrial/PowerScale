/** Набор символов декора области. */
export type MapDecorSet = 'grass' | 'tree' | 'pine' | 'field' | 'meadow' | 'mixed';

/** Одиночный символ, из которого собраны наборы. */
export type MapDecorSymbol = 'grass' | 'tree' | 'pine' | 'field';

/** Визуальный декор области; пусто — без декора. */
export interface MapAreaDecor {
  /** Плотность заливки, 0–100; нет — наследовать настройку карты. */
  fillOpacity?: number;
  /** Набор символов; нет — область без декора. */
  set?: MapDecorSet;
  /** Символов на 10 000 px². */
  density?: number;
  /** Масштаб символа, %. */
  scale?: number;
  /** Разброс позиции, 0–100. */
  jitter?: number;
  /** Зерно раскладки; 0 — id области. */
  seed?: number;
}
