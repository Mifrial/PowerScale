/** Настройки рисования и навигации канваса карты. */
export const MAP_CANVAS_CONFIG = {
  /** Размер мира, если у варианта нет подложки. */
  fallbackWidth: 1200,
  fallbackHeight: 900,
  minScale: 0.1,
  maxScale: 8,
  /** Радиус маркера узла на экране, CSS-пиксели. */
  nodeRadius: 7,
  /** Радиус попадания по узлу на экране, CSS-пиксели. */
  nodeHitRadius: 12,
  /** Допуск попадания по ленте на экране, CSS-пиксели. */
  lineHitRadius: 6,
  /** Радиус маркера вершины на экране, CSS-пиксели. */
  vertexRadius: 5,
  /** Допуск попадания по вершине на экране, CSS-пиксели. */
  vertexHitRadius: 9,
  /** Радиус ручки Безье на экране, CSS-пиксели. */
  handleRadius: 4,
  /** Допуск попадания по ручке Безье на экране, CSS-пиксели. */
  handleHitRadius: 9,
  /** Длина направляющей-заглушки ручки Безье на экране, CSS-пиксели. */
  handleStub: 26,
  /** Отступ ручки ширины от края ленты на экране, CSS-пиксели. */
  handleOffset: 14,
  /** Смещение указателя, после которого клик считается перетаскиванием. */
  dragThreshold: 4,
  areaFillAlpha: 0.28,
  areaStrokeWidth: 2,
  lineStrokeWidth: 1,
  draftLineWidth: 12,
  routeWidth: 5,
  gridStep: 100,
} as const;
