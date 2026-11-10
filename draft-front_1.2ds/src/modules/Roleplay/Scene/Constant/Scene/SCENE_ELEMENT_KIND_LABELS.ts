import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';

/** Подписи типов элементов. */
export const SCENE_ELEMENT_KIND_LABELS: Record<SceneElementKind, string> = {
  support: 'Опора',
  obstacle: 'Препятствие',
  opening: 'Проём',
  terrain: 'Террейн',
  decor: 'Декор',
  marker: 'Маркер',
};
