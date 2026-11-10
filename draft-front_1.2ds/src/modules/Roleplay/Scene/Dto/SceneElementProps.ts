/** Свойства элемента; набор зависит от kind. */
export interface SceneElementProps {
  kind?: string;
  height?: number;
  elevation?: number;
  blocksMovement?: boolean;
  blocksVision?: boolean;
  state?: 'open' | 'closed';
  speed?: number;
  label?: string;
  rotation?: number;
  scale?: number;
  role?: string;
  assetPublicId?: string;
}
