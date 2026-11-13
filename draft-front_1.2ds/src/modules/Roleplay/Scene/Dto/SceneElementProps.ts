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
  /** Радиус основания декора, проценты ширины спрайта: ход и ствол. */
  baseRadius?: number;
  /** Радиус силуэта декора, проценты ширины спрайта: крона, валун. */
  sightRadius?: number;
  /** Низ силуэта, проценты ширины спрайта: 0 — стоит на земле. */
  sightBottom?: number;
  role?: string;
  assetPublicId?: string;
  shoreKind?: string;
  shoreWidth?: number;
}
