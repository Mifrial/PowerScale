import type { ImageContentBox } from '@/modules/Core/File/Dto/ImageContentBox';

/** Размер и якорь спрайта: видимая масса задаёт масштаб, файл — поля. */
export interface SceneDecorSpritePlacement {
  /** Ширина спрайта в единицах сцены вместе с прозрачными полями. */
  width: number;
  /** Высота спрайта в единицах сцены. */
  height: number;
  /** Доля ширины файла, на которой стоит якорь. */
  anchorX: number;
  /** Доля высоты файла, на которой стоит якорь. */
  anchorY: number;
  /** Видимая ширина в единицах сцены. */
  visibleWidth: number;
  /** Видимая высота в единицах сцены. */
  visibleHeight: number;
}

/** Видимая рамка в пикселях, зажатая в размеры файла. */
function visibleFrame(
  box: ImageContentBox | null | undefined,
  textureWidth: number,
  textureHeight: number,
): ImageContentBox {
  const x = clamp(Math.round(box?.x ?? 0), 0, textureWidth - 1);
  const y = clamp(Math.round(box?.y ?? 0), 0, textureHeight - 1);

  return {
    x,
    y,
    width: clamp(Math.round(box?.width ?? textureWidth), 1, textureWidth - x),
    height: clamp(Math.round(box?.height ?? textureHeight), 1, textureHeight - y),
  };
}

/** Значение в границах. */
function clamp(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, value));
}

/**
 * Раскладка спрайта по видимой рамке.
 *
 * `visibleWidth` — это `scale` в единицах сцены. Спрайт растягивается так,
 * чтобы видимая масса получила ровно эту ширину, а якорь встал на центр её
 * низа: прозрачные поля не двигают объект и не раздувают его.
 */
export function sceneDecorSpritePlacement(
  box: ImageContentBox | null | undefined,
  textureWidth: number,
  textureHeight: number,
  visibleWidth: number,
): SceneDecorSpritePlacement {
  const width = Math.max(1, textureWidth);
  const height = Math.max(1, textureHeight);
  const visible = visibleFrame(box, width, height);
  const spriteWidth = (visibleWidth * width) / visible.width;

  return {
    width: spriteWidth,
    height: (spriteWidth * height) / width,
    anchorX: (visible.x + visible.width / 2) / width,
    anchorY: (visible.y + visible.height) / height,
    visibleWidth,
    visibleHeight: (visibleWidth * visible.height) / visible.width,
  };
}
