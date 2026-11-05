import type { AvatarCrop } from '@/modules/Core/User/Dto/AvatarCrop';
import type { CropImageSize } from '@/modules/Core/User/Dto/CropImageSize';
import type { CropViewport } from '@/modules/Core/User/Dto/CropViewport';
import { AVATAR_MAX_ZOOM } from '@/modules/Core/User/Constant/Avatar/AVATAR_MAX_ZOOM';

/** Геометрия кроппера аватара: масштаб, сдвиг и квадрат исходника. */
export class AvatarCropService {
  /** Минимальный масштаб, при котором картинка покрывает круг. */
  getMinScale(stageSize: number, image: CropImageSize): number {
    return stageSize / Math.min(image.width, image.height);
  }

  /** Зажимает масштаб и сдвиг так, чтобы картинка всегда покрывала круг. */
  clampViewport(viewport: CropViewport, stageSize: number, image: CropImageSize): CropViewport {
    const minimum = this.getMinScale(stageSize, image);
    const scale = this.clamp(viewport.scale, minimum, minimum * AVATAR_MAX_ZOOM);
    const maxOffsetX = Math.max(0, (image.width * scale - stageSize) / 2);
    const maxOffsetY = Math.max(0, (image.height * scale - stageSize) / 2);

    return {
      scale,
      offsetX: this.clamp(viewport.offsetX, -maxOffsetX, maxOffsetX),
      offsetY: this.clamp(viewport.offsetY, -maxOffsetY, maxOffsetY),
    };
  }

  /** Переводит положение сцены в квадрат кропа исходника. */
  getCropFromViewport(viewport: CropViewport, stageSize: number, image: CropImageSize): AvatarCrop {
    const side = Math.min(Math.round(stageSize / viewport.scale), image.width, image.height);
    const size = Math.max(1, side);
    const centerX = image.width / 2 - viewport.offsetX / viewport.scale;
    const centerY = image.height / 2 - viewport.offsetY / viewport.scale;

    return {
      x: this.clamp(Math.round(centerX - size / 2), 0, image.width - size),
      y: this.clamp(Math.round(centerY - size / 2), 0, image.height - size),
      size,
    };
  }

  /** Ставит картинку так, чтобы выбранный квадрат оказался в круге. */
  getViewportFromCrop(crop: AvatarCrop, stageSize: number, image: CropImageSize): CropViewport {
    const minimum = this.getMinScale(stageSize, image);
    const scale = this.clamp(stageSize / crop.size, minimum, minimum * AVATAR_MAX_ZOOM);
    const centerX = crop.x + crop.size / 2;
    const centerY = crop.y + crop.size / 2;

    return this.clampViewport(
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
  zoomTo(viewport: CropViewport, zoom: number, stageSize: number, image: CropImageSize): CropViewport {
    const minimum = this.getMinScale(stageSize, image);
    const scale = minimum * this.clamp(zoom, 1, AVATAR_MAX_ZOOM);
    const centerX = image.width / 2 - viewport.offsetX / viewport.scale;
    const centerY = image.height / 2 - viewport.offsetY / viewport.scale;

    return this.clampViewport(
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
  getZoom(viewport: CropViewport, stageSize: number, image: CropImageSize): number {
    return viewport.scale / this.getMinScale(stageSize, image);
  }

  private clamp(value: number, minimum: number, maximum: number): number {
    return Math.min(Math.max(value, minimum), maximum);
  }
}
