/** Слой рендерера локации, снизу вверх. */
export type SceneLayerName =
  | 'backdrop'
  | 'grid'
  | 'terrain'
  | 'waterPlane'
  | 'islands'
  | 'supports'
  | 'obstacles'
  | 'openings'
  | 'ground'
  /** Проходимые крыши: настил над домом, сортируется с акторами. */
  | 'deckRoofs'
  | 'decor'
  | 'roofs'
  | 'overlay'
  | 'fog'
  | 'route'
  | 'selection'
  | 'gizmos';
