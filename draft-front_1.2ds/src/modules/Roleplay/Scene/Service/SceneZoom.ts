/** Доля изменения зума, после которой слои надо пересобрать. */
export const SCENE_ZOOM_REBUILD_SHARE = 0.01;

/**
 * Нужно ли пересобрать слои под новый зум.
 *
 * Сравниваем с зумом, под который слои уже собраны, а не с предыдущим
 * событием колеса: толщины линий и тени считаются при сборке, поэтому при
 * плавном приближении их надо обновлять до двукратного шага. Порог в один
 * процент отсекает дрожание.
 */
export function sceneZoomNeedsRebuild(builtZoom: number, zoom: number): boolean {
  if (!Number.isFinite(builtZoom) || !Number.isFinite(zoom) || builtZoom <= 0 || zoom <= 0) {
    return false;
  }

  return Math.abs(zoom / builtZoom - 1) > SCENE_ZOOM_REBUILD_SHARE;
}
