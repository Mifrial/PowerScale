/** Тип поверхности клетки поля. */
export type SceneSurfaceKind =
  | 'grass'
  | 'dirt'
  | 'stone'
  | 'sand'
  | 'mud'
  | 'ice'
  | 'water'
  /** Пол из досок: паркет, дощатый настил. */
  | 'wood'
  /** Пол из плитки: камень, керамика. */
  | 'tile'
  | 'bridge'
  /** Проходимая крыша: настил над домом. */
  | 'roof'
  | 'none';
