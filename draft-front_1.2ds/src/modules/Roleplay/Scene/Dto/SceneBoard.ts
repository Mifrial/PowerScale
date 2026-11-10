import type { SceneBoardFile } from '@/modules/Roleplay/Scene/Dto/SceneBoardFile';

/** Одно 2D-поле внутри локации. */
export interface SceneBoard {
  id: number;
  name: string;
  sortOrder: number;
  baseFile: SceneBoardFile | null;
  boundWidth: number;
  boundHeight: number;
  occupancyCell: number;
  zQuantum: number;
  gridStep: number;
}
