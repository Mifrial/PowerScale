import type { SceneFootprint, SceneSilhouette } from '@/modules/Roleplay/Scene/Dto/SceneFootprint';

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
  /** Радиус основания декора, проценты видимой ширины спрайта: ход и ствол. */
  baseRadius?: number;
  /** Радиус силуэта декора, проценты видимой ширины спрайта: крона, валун. */
  sightRadius?: number;
  /** Низ силуэта, проценты видимой ширины спрайта: 0 — стоит на земле. */
  sightBottom?: number;
  /** Основание декора в единицах сцены: круг или полигон от якоря. */
  footprint?: SceneFootprint;
  /** Силуэт декора в единицах сцены: форма перекрытия обзора и его низ. */
  silhouette?: SceneSilhouette;
  role?: string;
  assetPublicId?: string;
  shoreKind?: string;
  shoreWidth?: number;
  /**
   * Глубина воды в единицах сцены: насколько русло ниже берега.
   *
   * `0` — вода вровень с берегом, как в старых сценах. Врезанное русло
   * опускает поверхность воды, а береговая полоса (`shoreWidth`) становится
   * наклонным откосом от берега к урезу.
   */
  depth?: number;
  /**
   * Террейн лежит поверх воды: остров, насыпь, полуостров.
   *
   * Вода рисуется над сушей, поэтому без этого признака трава-остров утонула
   * бы под озером. Контур с признаком перекрывает воду и в рисунке, и в сетке
   * поверхностей.
   */
  overWater?: boolean;
}
