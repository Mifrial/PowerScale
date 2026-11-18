/** Мебель: подтипы препятствия с процедурным рисунком сверху. */
export type SceneFurnitureKind =
  'bed' | 'table' | 'chair' | 'bench' | 'chest' | 'wardrobe' | 'shelf' | 'barrel' | 'rug' | 'fireplace';

/** Свойства мебели по подтипу: высота и поведение по умолчанию. */
export interface SceneFurnitureOption {
  value: SceneFurnitureKind;
  title: string;
  icon: string;
  /** Высота в единицах сцены при точности 64: четверть ипари — 16. */
  height: number;
  /** Материал корпуса по умолчанию. */
  material: string;
  /** Ковёр лежит на полу и не мешает ходу. */
  blocksMovement: boolean;
  /** Высокие шкафы и камины перекрывают обзор, низкая мебель — нет. */
  blocksVision: boolean;
}

/**
 * Набор мебели для дома.
 *
 * Высота — не украшение: до `SCENE_NAVIGATION.climbIpari` на предмет можно
 * забраться, поэтому стол, кровать и лавка дают верхний уровень навигации.
 * Шкаф, полка и камин выше порога и остаются препятствиями.
 */
export const SCENE_FURNITURE_OPTIONS: readonly SceneFurnitureOption[] = [
  {
    value: 'bed',
    title: 'Кровать',
    icon: 'mdi-bed',
    height: 48,
    material: 'dark',
    blocksMovement: true,
    blocksVision: false,
  },
  {
    value: 'table',
    title: 'Стол',
    icon: 'mdi-table-furniture',
    height: 51,
    material: 'wood',
    blocksMovement: true,
    blocksVision: false,
  },
  {
    value: 'chair',
    title: 'Стул',
    icon: 'mdi-seat',
    height: 58,
    material: 'wood',
    blocksMovement: true,
    blocksVision: false,
  },
  {
    value: 'bench',
    title: 'Лавка',
    icon: 'mdi-bench',
    height: 58,
    material: 'wood',
    blocksMovement: true,
    blocksVision: false,
  },
  {
    value: 'chest',
    title: 'Сундук',
    icon: 'mdi-treasure-chest',
    height: 45,
    material: 'dark',
    blocksMovement: true,
    blocksVision: false,
  },
  {
    value: 'wardrobe',
    title: 'Шкаф',
    icon: 'mdi-wardrobe',
    height: 128,
    material: 'dark',
    blocksMovement: true,
    blocksVision: true,
  },
  {
    value: 'shelf',
    title: 'Полка',
    icon: 'mdi-bookshelf',
    height: 115,
    material: 'wood',
    blocksMovement: true,
    blocksVision: true,
  },
  {
    value: 'barrel',
    title: 'Бочка',
    icon: 'mdi-barrel',
    height: 58,
    material: 'wood',
    blocksMovement: true,
    blocksVision: false,
  },
  {
    value: 'rug',
    title: 'Ковёр',
    icon: 'mdi-rug',
    height: 0,
    material: 'fabric',
    blocksMovement: false,
    blocksVision: false,
  },
  {
    value: 'fireplace',
    title: 'Камин',
    icon: 'mdi-fireplace',
    height: 96,
    material: 'stone',
    blocksMovement: true,
    blocksVision: true,
  },
];

/** Материалы корпуса мебели. */
export const SCENE_FURNITURE_MATERIALS = [
  { value: 'wood', title: 'Дерево' },
  { value: 'dark', title: 'Тёмное дерево' },
  { value: 'stone', title: 'Камень' },
  { value: 'fabric', title: 'Ткань' },
] as const;

/** Палитра материала: база, светлая грань, тень и обводка. */
export const SCENE_FURNITURE_PALETTE = {
  wood: { base: 0xb98552, light: 0xd0a06a, dark: 0x8a5a35, edge: 0x3b2414 },
  dark: { base: 0x8a5a35, light: 0xa9743f, dark: 0x5c3a22, edge: 0x2a170c },
  stone: { base: 0x9a968c, light: 0xb3afa5, dark: 0x7a766d, edge: 0x45423c },
  fabric: { base: 0x9a4b3a, light: 0xb96d54, dark: 0x6e3228, edge: 0x3f1a13 },
} as const;

/** Материал с проверкой: незнакомый — по умолчанию у подтипа. */
export function sceneFurnitureMaterial(
  kind: SceneFurnitureKind,
  material: string | undefined,
): keyof typeof SCENE_FURNITURE_PALETTE {
  const option = SCENE_FURNITURE_OPTIONS.find((item) => item.value === kind);
  const fallback = option?.material ?? 'wood';
  const value = material ?? fallback;

  return value in SCENE_FURNITURE_PALETTE
    ? (value as keyof typeof SCENE_FURNITURE_PALETTE)
    : (fallback as keyof typeof SCENE_FURNITURE_PALETTE);
}

/** Подтип мебели по строке; `null` — это не мебель. */
export function sceneFurnitureKind(kind: string | undefined): SceneFurnitureKind | null {
  return SCENE_FURNITURE_OPTIONS.find((item) => item.value === kind)?.value ?? null;
}
