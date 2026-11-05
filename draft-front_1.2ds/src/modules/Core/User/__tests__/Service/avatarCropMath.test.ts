import { describe, expect, it } from 'vitest';
import {
  clampViewport,
  cropFromViewport,
  minScale,
  viewportFromCrop,
  zoomOf,
  zoomTo,
} from '@/modules/Core/User/Service/AvatarCropMath';

const image = { width: 2000, height: 1000 };
const stage = 320;

describe('AvatarCropMath', () => {
  it('переводит кроп в положение сцены и обратно', () => {
    const crop = { x: 300, y: 100, size: 700 };
    const viewport = viewportFromCrop(crop, stage, image);
    const restored = cropFromViewport(viewport, stage, image);

    expect(restored.x).toBeCloseTo(crop.x, 0);
    expect(restored.y).toBeCloseTo(crop.y, 0);
    expect(restored.size).toBeCloseTo(crop.size, 0);
  });

  it('не выпускает картинку из круга', () => {
    const viewport = clampViewport({ scale: minScale(stage, image), offsetX: 10_000, offsetY: -10_000 }, stage, image);
    const crop = cropFromViewport(viewport, stage, image);

    expect(crop.x).toBeGreaterThanOrEqual(0);
    expect(crop.y).toBeGreaterThanOrEqual(0);
    expect(crop.x + crop.size).toBeLessThanOrEqual(image.width);
    expect(crop.y + crop.size).toBeLessThanOrEqual(image.height);
    expect(crop.size).toBe(Math.min(image.width, image.height));
  });

  it('зум ограничен минимумом и восьмикратным размером', () => {
    const minimum = minScale(stage, image);
    const zoomedOut = zoomTo({ scale: minimum, offsetX: 0, offsetY: 0 }, 0.1, stage, image);
    const zoomedIn = zoomTo({ scale: minimum, offsetX: 0, offsetY: 0 }, 100, stage, image);

    expect(zoomOf(zoomedOut, stage, image)).toBeCloseTo(1, 5);
    expect(zoomOf(zoomedIn, stage, image)).toBeCloseTo(8, 5);
  });

  it('кроп всегда квадрат и не больше исходника', () => {
    const viewport = viewportFromCrop({ x: 0, y: 0, size: 1000 }, stage, image);
    const crop = cropFromViewport(viewport, stage, image);

    expect(crop.size).toBeGreaterThan(0);
    expect(crop.size).toBeLessThanOrEqual(Math.min(image.width, image.height));
  });
});
