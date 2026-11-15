import type { SceneHandle } from '@/modules/Roleplay/Scene/Dto/SceneHandle';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/**
 * Основание объекта: круг или полигон в единицах сцены.
 *
 * Точки и `offset` локальны: они отсчитываются от якоря элемента и
 * поворачиваются вместе с `props.rotation`. `offset` двигает всю форму
 * относительно спрайта, не меняя её точек.
 */
export type SceneFootprint =
  | { shape: 'circle'; radius: number; offset?: ScenePoint }
  | { shape: 'polygon'; points: ScenePoint[]; handles?: SceneHandle[]; offset?: ScenePoint };

/**
 * Силуэт: форма перекрытия обзора плюс низ над землёй.
 *
 * `bottom` — на какой высоте начинается перекрытие. У кроны дерева оно выше
 * глаз, у валуна и стены — от земли.
 */
export type SceneSilhouette = SceneFootprint & { bottom: number };
