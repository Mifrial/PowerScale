import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/**
 * Основание объекта: круг или полигон в единицах сцены.
 *
 * Точки полигона локальны: они отсчитываются от якоря элемента и
 * поворачиваются вместе с `props.rotation`. Поэтому перенос и поворот объекта
 * не требуют правки контура.
 */
export type SceneFootprint = { shape: 'circle'; radius: number } | { shape: 'polygon'; points: ScenePoint[] };

/**
 * Силуэт: форма перекрытия обзора плюс низ над землёй.
 *
 * `bottom` — на какой высоте начинается перекрытие. У кроны дерева оно выше
 * глаз, у валуна и стены — от земли.
 */
export type SceneSilhouette = SceneFootprint & { bottom: number };
