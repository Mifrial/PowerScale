import { describe, expect, it } from 'vitest';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import {
  sceneDecorFootprint,
  sceneDecorFootprintDefaults,
  sceneDecorSilhouette,
  spriteSize,
} from '@/modules/Roleplay/Scene/Service/SceneDecorFootprint';

const RESOLUTION = 64;

const point = (x = 100, y = 100): SceneShape => ({ type: 'point', x, y });

const decor = (props: SceneElementProps, shape: SceneShape = point()) => ({ shape, props });

describe('SceneDecorFootprint', () => {
  it('берёт явный круг основания в единицах сцены', () => {
    const footprint = sceneDecorFootprint(decor({ footprint: { shape: 'circle', radius: 24 } }), RESOLUTION);

    expect(footprint).toEqual({ shape: 'circle', center: [100, 100], radius: 24 });
  });

  it('переносит и поворачивает полигон основания', () => {
    const points: ScenePoint[] = [
      [0, -10],
      [10, 0],
      [0, 10],
      [-10, 0],
    ];
    const footprint = sceneDecorFootprint(decor({ footprint: { shape: 'polygon', points }, rotation: 90 }), RESOLUTION);

    // Поворот на 90°: (x, y) → (−y, x), затем перенос в якорь.
    expect(footprint).toEqual({
      shape: 'polygon',
      points: [
        [110, 100],
        [100, 110],
        [90, 100],
        [100, 90],
      ],
    });
  });

  it('считает старое основание долей ширины спрайта', () => {
    const footprint = sceneDecorFootprint(decor({ scale: 100, baseRadius: 20 }), RESOLUTION);

    expect(spriteSize(decor({ scale: 100 }), RESOLUTION)).toBe(64);
    expect(footprint).toEqual({ shape: 'circle', center: [100, 100], radius: 12.8 });
  });

  it('берёт явный силуэт с низом в единицах сцены', () => {
    const silhouette = sceneDecorSilhouette(
      decor({
        silhouette: {
          shape: 'polygon',
          points: [
            [-20, -20],
            [20, -20],
            [20, 20],
            [-20, 20],
          ],
          bottom: 16,
        },
      }),
      RESOLUTION,
    );

    expect(silhouette?.bottom).toBe(16);
    expect(silhouette?.area.shape).toBe('polygon');
  });

  it('переводит старый силуэт в те же единицы', () => {
    const silhouette = sceneDecorSilhouette(decor({ scale: 100, sightRadius: 45, sightBottom: 85 }), RESOLUTION);

    expect(silhouette?.area).toEqual({ shape: 'circle', center: [100, 100], radius: 28.8 });
    // 85% ширины спрайта — это 0.85 ипари, то есть 54.4 единицы.
    expect(silhouette?.bottom).toBeCloseTo(54.4);
  });

  it('не считает основание для не-точки', () => {
    const rect = decor({ footprint: { shape: 'circle', radius: 10 } }, { type: 'rect', x: 0, y: 0, w: 10, h: 10 });

    expect(sceneDecorFootprint(rect, RESOLUTION)).toBeNull();
    expect(sceneDecorSilhouette(rect, RESOLUTION)).toBeNull();
  });

  it('показывает карточке явные поля или старые доли', () => {
    const explicit = sceneDecorFootprintDefaults(
      decor({
        footprint: { shape: 'circle', radius: 20 },
        silhouette: { shape: 'circle', radius: 30, bottom: 12 },
      }),
      RESOLUTION,
    );
    expect(explicit.footprint).toEqual({ shape: 'circle', radius: 20 });
    expect(explicit.silhouette).toEqual({ shape: 'circle', radius: 30, bottom: 12 });

    const legacy = sceneDecorFootprintDefaults(
      decor({ scale: 100, baseRadius: 20, sightRadius: 45, sightBottom: 85 }),
      RESOLUTION,
    );
    expect(legacy.footprint).toEqual({ shape: 'circle', radius: 13 });
    expect(legacy.silhouette).toEqual({ shape: 'circle', radius: 29, bottom: 54 });
  });
});
