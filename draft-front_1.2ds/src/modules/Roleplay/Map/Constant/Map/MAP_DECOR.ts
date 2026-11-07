import type { MapAreaDecor, MapDecorSet, MapDecorSymbol } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';

/** Слой набора: какой символ и с какой долей участвует в смеси. */
export interface MapDecorLayer {
  symbol: MapDecorSymbol;
  share: number;
}

/** Готовый набор символов и его стартовые значения. */
export interface MapDecorSetInfo {
  title: string;
  layers: MapDecorLayer[];
  /** Символов на 10 000 px². */
  density: number;
  /** Разброс позиции, 0–100. */
  jitter: number;
  /** Масштаб символа, %. */
  scale: number;
}

/** Базовый размер символа в пикселях подложки при масштабе 100 %. */
export const MAP_DECOR_SYMBOL_SIZE: Record<MapDecorSymbol, number> = {
  grass: 10,
  tree: 26,
  pine: 26,
  field: 13,
};

/** Наборы: одиночные символы и типовые смеси. */
export const MAP_DECOR_SETS: Record<MapDecorSet, MapDecorSetInfo> = {
  grass: {
    title: 'Трава',
    layers: [{ symbol: 'grass', share: 1 }],
    density: 60,
    jitter: 85,
    scale: 100,
  },
  tree: {
    title: 'Кроны',
    layers: [{ symbol: 'tree', share: 1 }],
    density: 25,
    jitter: 90,
    scale: 100,
  },
  pine: {
    title: 'Ели',
    layers: [{ symbol: 'pine', share: 1 }],
    density: 25,
    jitter: 90,
    scale: 100,
  },
  field: {
    title: 'Жнивьё',
    layers: [{ symbol: 'field', share: 1 }],
    density: 70,
    jitter: 75,
    scale: 100,
  },
  meadow: {
    title: 'Луг',
    layers: [
      { symbol: 'grass', share: 0.85 },
      { symbol: 'tree', share: 0.15 },
    ],
    density: 55,
    jitter: 85,
    scale: 100,
  },
  mixed: {
    title: 'Смешанный лес',
    layers: [
      { symbol: 'grass', share: 0.7 },
      { symbol: 'pine', share: 0.15 },
      { symbol: 'tree', share: 0.15 },
    ],
    density: 30,
    jitter: 90,
    scale: 100,
  },
};

/** Варианты для выпадающего списка. */
export const MAP_DECOR_OPTIONS = (Object.keys(MAP_DECOR_SETS) as MapDecorSet[]).map((value) => ({
  value,
  title: MAP_DECOR_SETS[value].title,
}));

/** Границы полей декора. */
export const MAP_DECOR_LIMITS = {
  density: { min: 0, max: 200 },
  scale: { min: 40, max: 250 },
  jitter: { min: 0, max: 100 },
  seed: { min: 0, max: 1000000 },
} as const;

/** Проверяет, что код набора известен. */
export function isMapDecorSet(value: unknown): value is MapDecorSet {
  return typeof value === 'string' && value in MAP_DECOR_SETS;
}

/** Полный декор набора — то, что заполняет пресет области. */
export function decorFromSet(set: MapDecorSet, overrides: Partial<MapAreaDecor> = {}): MapAreaDecor {
  const info = MAP_DECOR_SETS[set];

  return {
    set,
    density: info.density,
    scale: info.scale,
    jitter: info.jitter,
    ...overrides,
  };
}
