/** Часть элемента, которую редактируют вершинами. */
export type SceneVertexPart = 'shape' | 'footprint';

/** Точка, которую сейчас редактируют ручками. */
export interface SceneVertexTarget {
  elementId: number;
  /** Геометрия элемента или основание декора; по умолчанию — фигура. */
  part?: SceneVertexPart;
  index: number;
}
