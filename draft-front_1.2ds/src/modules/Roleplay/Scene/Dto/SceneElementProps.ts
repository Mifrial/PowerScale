/** Свойства элемента; набор зависит от kind. */
export interface SceneElementProps {
  kind?: string;
  height?: number;
  /** Скорость поверхности: 100 — норма, 0 — непроходимо. */
  speed?: number;
  elevation?: number;
  blocksMovement?: boolean;
  blocksVision?: boolean;
  state?: 'open' | 'closed';
  label?: string;
  rotation?: number;
  scale?: number;
  role?: string;
  assetPublicId?: string;
  shoreKind?: string;
  shoreWidth?: number;
}
