import type { Texture } from 'pixi.js';

/** Параметры рваной кромки террейна: материал и шум, который её размывает. */
export interface SceneTerrainBlendOptions {
  /** Текстура материала, который наползает на соседа. */
  material: Texture;
  /** Период тайла материала в единицах сцены. */
  period: number;
  /** Сдвиг тайла: тот же, что у заливки поверхности. */
  offset: readonly [number, number];
  /** Крупная вариация материала; `null` — не подмешивать. */
  macro: Texture | null;
  /** Период тайла крупной вариации в единицах сцены. */
  macroPeriod: number;
  /** Сдвиг тайла крупной вариации. */
  macroOffset: readonly [number, number];
  /** Доля крупной вариации в цвете кромки. */
  macroMix: number;
  /** Мелкая деталь материала (лезвия травы); `null` — не подмешивать. */
  overlay: Texture | null;
  /** Период тайла мелкой детали в единицах сцены. */
  overlayPeriod: number;
  /** Сдвиг тайла мелкой детали. */
  overlayOffset: readonly [number, number];
  /** Доля мелкой детали в цвете кромки. */
  overlayMix: number;
  /** Ширина сплошной части полосы, единицы сцены. */
  solid: number;
  /** Ширина рваной части полосы, единицы сцены. */
  ragged: number;
  /** Нижняя доля рваной части: кромка не сходит на нет. */
  base: number;
  /** Размах шума рваной части. */
  amp: number;
  /** Крупный шум кромки, ячеек на единицу сцены. */
  noiseScale: number;
  /** Мелкий шум кромки, ячеек на единицу сцены. */
  fineScale: number;
  /** Вклад мелкого шума в ширину кромки. */
  fineAmp: number;
  /** Мягкость края, единицы сцены. */
  soft: number;
  /** Общая прозрачность полосы. */
  alpha: number;
  /** Сдвиг шума: разводит кромки соседних элементов. */
  seed: readonly [number, number];
}
