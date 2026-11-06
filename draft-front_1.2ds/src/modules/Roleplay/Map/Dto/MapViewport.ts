/** Положение и масштаб вида карты: world → screen. */
export interface MapViewport {
  /** Пикселей экрана на пиксель подложки. */
  scale: number;
  /** Смещение начала координат мира по X на экране. */
  offsetX: number;
  /** Смещение начала координат мира по Y на экране. */
  offsetY: number;
}
