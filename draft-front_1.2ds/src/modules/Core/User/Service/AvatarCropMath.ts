import type { AvatarCrop } from '@/modules/Core/User/Dto/AvatarCrop';

/** Размер картинки в пикселях. */
export interface CropImageSize {
  width: number;
  height: number;
}

/** Положение картинки относительно центра сцены. */
export interface CropViewport {
  scale: number;
  offsetX: number;
  offsetY: number;
}

const MAX_ZOOM = 8;

function clamp(value: number, minimum: number, maximum: number): number {
  return Math.min(Math.max(value, minimum), maximum);
}

/** Минимальный масштаб, при котором картинка покрывает круг. */
export function minScale(stageSize: number, image: CropImageSize): number {
  return stageSize / Math.min(image.width, image.height);
}

/** Зажимает масштаб и сдвиг так, чтобы картинка всегда покрывала круг. */
export function clampViewport(viewport: CropViewport, stageSize: number, image: CropImageSize): CropViewport {
  const minimum = minScale(stageSize, image);
  const scale = clamp(viewport.scale, minimum, minimum * MAX_ZOOM);
  const maxOffsetX = Math.max(0, (image.width * scale - stageSize) / 2);
  const maxOffsetY = Math.max(0, (image.height * scale - stageSize) / 2);

  return {
    scale,
    offsetX: clamp(viewport.offsetX, -maxOffsetX, maxOffsetX),
    offsetY: clamp(viewport.offsetY, -maxOffsetY, maxOffsetY),
  };
}

/** Переводит положение сцены в квадрат кропа исходника. */
export function cropFromViewport(viewport: CropViewport, stageSize: number, image: CropImageSize): AvatarCrop {
  const side = Math.min(Math.round(stageSize / viewport.scale), image.width, image.height);
  const size = Math.max(1, side);
  const centerX = image.width / 2 - viewport.offsetX / viewport.scale;
  const centerY = image.height / 2 - viewport.offsetY / viewport.scale;

  return {
    x: clamp(Math.round(centerX - size / 2), 0, image.width - size),
    y: clamp(Math.round(centerY - size / 2), 0, image.height - size),
    size,
  };
}

/** Ставит картинку так, чтобы выбранный квадрат оказался в круге. */
export function viewportFromCrop(crop: AvatarCrop, stageSize: number, image: CropImageSize): CropViewport {
  const minimum = minScale(stageSize, image);
  const scale = clamp(stageSize / crop.size, minimum, minimum * MAX_ZOOM);
  const centerX = crop.x + crop.size / 2;
  const centerY = crop.y + crop.size / 2;

  return clampViewport(
    {
      scale,
      offsetX: (image.width / 2 - centerX) * scale,
      offsetY: (image.height / 2 - centerY) * scale,
    },
    stageSize,
    image,
  );
}

/** Меняет зум, сохраняя точку в центре круга. */
export function zoomTo(viewport: CropViewport, zoom: number, stageSize: number, image: CropImageSize): CropViewport {
  const minimum = minScale(stageSize, image);
  const scale = minimum * clamp(zoom, 1, MAX_ZOOM);
  const centerX = image.width / 2 - viewport.offsetX / viewport.scale;
  const centerY = image.height / 2 - viewport.offsetY / viewport.scale;

  return clampViewport(
    {
      scale,
      offsetX: (image.width / 2 - centerX) * scale,
      offsetY: (image.height / 2 - centerY) * scale,
    },
    stageSize,
    image,
  );
}

/** Текущий зум относительно минимума. */
export function zoomOf(viewport: CropViewport, stageSize: number, image: CropImageSize): number {
  return viewport.scale / minScale(stageSize, image);
}
