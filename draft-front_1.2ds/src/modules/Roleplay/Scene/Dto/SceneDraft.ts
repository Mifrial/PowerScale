import type { SceneDraftBoard } from '@/modules/Roleplay/Scene/Dto/SceneDraftBoard';
import type { SceneSaveElement } from '@/modules/Roleplay/Scene/Dto/SceneSaveElement';
import type { SceneSaveEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneSaveEnclosure';

/** Черновик локации в редакторе: снимок плюс состояние строки. */
export interface SceneDraft {
  /** Id локации; null — ещё не сохранена. */
  id: number | null;
  version: number;
  canEdit: boolean;
  activeBoardId: number | null;
  name: string;
  description: string;
  resolution: number;
  schemaVersion: number;
  boards: SceneDraftBoard[];
  elements: SceneSaveElement[];
  enclosures: SceneSaveEnclosure[];
}
