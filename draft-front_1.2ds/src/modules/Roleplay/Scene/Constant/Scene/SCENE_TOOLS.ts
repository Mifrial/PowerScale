import type { SceneToolDefinition } from '@/modules/Roleplay/Scene/Dto/SceneToolDefinition';

/** Инструменты редактора локации. */
export const SCENE_TOOLS: SceneToolDefinition[] = [
  { id: 'select', label: 'Выбор', icon: 'mdi-cursor-default', kind: null, shapeType: null, props: {} },
  {
    id: 'obstacle',
    label: 'Препятствие',
    icon: 'mdi-wall',
    kind: 'obstacle',
    shapeType: 'rect',
    props: { blocksMovement: true, blocksVision: true, height: 100 },
  },
  {
    id: 'wall',
    label: 'Стена',
    icon: 'mdi-dots-horizontal',
    kind: 'obstacle',
    shapeType: 'wall',
    props: { blocksMovement: true, blocksVision: true, height: 100 },
  },
  {
    id: 'support',
    label: 'Опора',
    icon: 'mdi-floor-plan',
    kind: 'support',
    shapeType: 'rect',
    props: { kind: 'deck', height: 50, elevation: 0 },
  },
  {
    id: 'terrain',
    label: 'Террейн',
    icon: 'mdi-terrain',
    kind: 'terrain',
    shapeType: 'rect',
    props: { speed: 5000, label: '' },
  },
  {
    id: 'opening',
    label: 'Проём',
    icon: 'mdi-door',
    kind: 'opening',
    shapeType: 'wall',
    props: { kind: 'door', state: 'closed', blocksVision: false },
  },
  {
    id: 'decor',
    label: 'Декор',
    icon: 'mdi-forest',
    kind: 'decor',
    shapeType: 'point',
    props: { rotation: 0, scale: 100, label: '' },
  },
  {
    id: 'marker',
    label: 'Маркер',
    icon: 'mdi-map-marker',
    kind: 'marker',
    shapeType: 'point',
    props: { role: 'entrance', label: '' },
  },
];
