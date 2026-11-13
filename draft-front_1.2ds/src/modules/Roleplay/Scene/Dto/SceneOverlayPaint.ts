/** Прямоугольная заливка оверлея в мировых координатах. */
export interface SceneOverlayBand {
  x: number;
  y: number;
  width: number;
  height: number;
  color: number;
  alpha: number;
}

/**
 * Готовая картинка оверлеев для рендерера.
 *
 * Расчёт поверхностей и видимости остаётся в сервисах; рендерер только рисует
 * полосы в порядке массива: сначала стоимость, затем затемнение видимости.
 */
export interface SceneOverlayPaint {
  bands: SceneOverlayBand[];
}
