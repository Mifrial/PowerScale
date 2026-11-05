import { describe, expect, it } from 'vitest';
import { AvatarCropService } from '@/modules/Core/User/Service/AvatarCropService';

const service = new AvatarCropService();
const image = { width: 2000, height: 1000 };
const stage = 320;

describe('AvatarCropService', () => {
  it('переводит кроп в положение сцены и обратно', () => {
    const crop = { x: 300, y: 100, size: 700 };
    const viewport = service.getViewportFromCrop(crop, stage, image);
    const restored = service.getCropFromViewport(viewport, stage, image);

    expect(restored.x).toBeCloseTo(crop.x, 0);
    expect(restored.y).toBeCloseTo(crop.y, 0);
    expect(restored.size).toBeCloseTo(crop.size, 0);
  });

  it('не выпускает картинку из круга', () => {
    const viewport = service.clampViewport(
      { scale: service.getMinScale(stage, image), offsetX: 10_000, offsetY: -10_000 },
      stage,
      image,
    );
    const crop = service.getCropFromViewport(viewport, stage, image);

    expect(crop.x).toBeGreaterThanOrEqual(0);
    expect(crop.y).toBeGreaterThanOrEqual(0);
    expect(crop.x + crop.size).toBeLessThanOrEqual(image.width);
    expect(crop.y + crop.size).toBeLessThanOrEqual(image.height);
    expect(crop.size).toBe(Math.min(image.width, image.height));
  });

  it('зум ограничен минимумом и восьмикратным размером', () => {
    const minimum = service.getMinScale(stage, image);
    const zoomedOut = service.zoomTo({ scale: minimum, offsetX: 0, offsetY: 0 }, 0.1, stage, image);
    const zoomedIn = service.zoomTo({ scale: minimum, offsetX: 0, offsetY: 0 }, 100, stage, image);

    expect(service.getZoom(zoomedOut, stage, image)).toBeCloseTo(1, 5);
    expect(service.getZoom(zoomedIn, stage, image)).toBeCloseTo(8, 5);
  });

  it('кроп всегда квадрат и не больше исходника', () => {
    const viewport = service.getViewportFromCrop({ x: 0, y: 0, size: 1000 }, stage, image);
    const crop = service.getCropFromViewport(viewport, stage, image);

    expect(crop.size).toBeGreaterThan(0);
    expect(crop.size).toBeLessThanOrEqual(Math.min(image.width, image.height));
  });
});
