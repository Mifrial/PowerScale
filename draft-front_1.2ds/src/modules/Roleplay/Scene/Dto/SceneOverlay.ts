import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/** Настройки тактических оверлеев предпросмотра. */
export interface SceneOverlay {
  /** Заливка клеток по стоимости прохода. */
  costs: boolean;
  /** Затемнение клеток вне обзора. */
  visibility: boolean;
  /** Точка обзора в единицах сцены; null — центр поля. */
  observer: ScenePoint | null;
  /** Радиус обзора в единицах сцены; null — значение по умолчанию. */
  radius: number | null;
}
