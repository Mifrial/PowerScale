import type { MapToolMode } from '@/modules/Roleplay/Map/Enum/MapToolMode';

/** Инструменты редактора карты. */
export const MAP_TOOL_OPTIONS: { value: MapToolMode; title: string; icon: string }[] = [
  { value: 'view', title: 'Выбор и панорама', icon: 'mdi-cursor-default-outline' },
  { value: 'node', title: 'Место', icon: 'mdi-map-marker-plus-outline' },
  { value: 'area', title: 'Полигон', icon: 'mdi-vector-polygon' },
  { value: 'rect', title: 'Прямоугольник', icon: 'mdi-rectangle-outline' },
  { value: 'line', title: 'Лента', icon: 'mdi-waves' },
  { value: 'ruler', title: 'Линейка', icon: 'mdi-ruler' },
  { value: 'calibrate', title: 'Калибровка масштаба', icon: 'mdi-ruler-square' },
  { value: 'recognize', title: 'Объекты по подложке', icon: 'mdi-vector-square' },
];
