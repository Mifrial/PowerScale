import type { SceneSaveBoard } from '@/modules/Roleplay/Scene/Dto/SceneSaveBoard';

/** Поле черновика: снимок плюс размеры подложки для предупреждения о пропорциях. */
export interface SceneDraftBoard extends SceneSaveBoard {
  fileWidth: number | null;
  fileHeight: number | null;
}
