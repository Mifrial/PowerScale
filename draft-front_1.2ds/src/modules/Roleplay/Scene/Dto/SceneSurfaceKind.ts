/** Тип поверхности клетки поля. */
export type SceneSurfaceKind =
  | 'grass'
  | 'dirt'
  | 'stone'
  | 'sand'
  | 'mud'
  | 'ice'
  | 'water'
  | 'bridge'
  /** Проходимая крыша: настил над домом. */
  | 'roof'
  | 'none';
