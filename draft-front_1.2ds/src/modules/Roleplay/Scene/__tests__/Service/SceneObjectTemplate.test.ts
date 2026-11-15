import { describe, expect, it } from 'vitest';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import {
  parseSceneObjectTemplate,
  sceneObjectMeta,
  sceneObjectTemplateOf,
  sceneObjectTemplateProps,
} from '@/modules/Roleplay/Scene/Service/SceneObjectTemplate';

const RESOLUTION = 64;

const decor = (props: SceneElementProps) => ({ shape: { type: 'point' as const, x: 100, y: 200 }, props });

describe('SceneObjectTemplate', () => {
  it('сохраняет явное основание и силуэт в единицах сцены', () => {
    const element = decor({
      scale: 320,
      speed: 100,
      blocksVision: false,
      rotation: 90,
      footprint: { shape: 'circle', radius: 21, offset: [4, -2] },
      silhouette: { shape: 'circle', radius: 92, bottom: 174, offset: [0, 0] },
    });

    expect(sceneObjectTemplateOf(element, RESOLUTION)).toEqual({
      version: 1,
      kind: 'decor',
      resolution: RESOLUTION,
      scale: 320,
      footprint: { shape: 'circle', radius: 21, offset: [4, -2] },
      silhouette: { shape: 'circle', radius: 92, bottom: 174, offset: [0, 0] },
      speed: 100,
      blocksVision: false,
      rotation: 90,
    });
  });

  it('переводит старые проценты спрайта в единицы сцены', () => {
    const template = sceneObjectTemplateOf(
      decor({ scale: 320, baseRadius: 10, sightRadius: 45, sightBottom: 85 }),
      RESOLUTION,
    );

    // 320% от 64 = 204.8: 10% → 20, 45% → 92, 85% → 174.
    expect(template?.footprint).toEqual({ shape: 'circle', radius: 20 });
    expect(template?.silhouette).toEqual({ shape: 'circle', radius: 92, bottom: 174 });
    expect(template?.speed).toBe(0);
    expect(template?.blocksVision).toBe(true);
  });

  it('переносит шаблон на другое разрешение поля', () => {
    const template = sceneObjectTemplateOf(
      decor({
        footprint: {
          shape: 'polygon',
          points: [
            [-20, -20],
            [20, -20],
            [20, 20],
            [-20, 20],
          ],
          handles: [
            { in: [0, 0], out: [8, 0] },
            { in: [-8, 0], out: [0, 0] },
            { in: [0, 0], out: [0, 0] },
            { in: [0, 0], out: [0, 0] },
          ],
          offset: [8, -4],
        },
        silhouette: { shape: 'circle', radius: 60, bottom: 40, top: 80 },
      }),
      RESOLUTION,
    );

    const props = sceneObjectTemplateProps(sceneObjectMeta(template!), 32);

    expect(props?.footprint).toEqual({
      shape: 'polygon',
      points: [
        [-10, -10],
        [10, -10],
        [10, 10],
        [-10, 10],
      ],
      handles: [
        { in: [0, 0], out: [4, 0] },
        { in: [-4, 0], out: [0, 0] },
        { in: [0, 0], out: [0, 0] },
        { in: [0, 0], out: [0, 0] },
      ],
      offset: [4, -2],
    });
    expect(props?.silhouette).toEqual({ shape: 'circle', radius: 30, bottom: 20, top: 40 });
  });

  it('не применяет незнакомый или битый шаблон', () => {
    expect(sceneObjectTemplateProps(null, RESOLUTION)).toBeNull();
    expect(sceneObjectTemplateProps({}, RESOLUTION)).toBeNull();
    expect(sceneObjectTemplateProps({ sceneObject: { version: 2, kind: 'decor' } }, RESOLUTION)).toBeNull();
    expect(
      sceneObjectTemplateProps(
        {
          sceneObject: {
            version: 1,
            kind: 'decor',
            resolution: 64,
            footprint: {
              shape: 'polygon',
              points: [
                [0, 0],
                [10, 10],
              ],
            },
            silhouette: { shape: 'circle', radius: 10, bottom: 0 },
          },
        },
        RESOLUTION,
      ),
    ).toBeNull();
    expect(parseSceneObjectTemplate({ sceneObject: [] })).toBeNull();
  });

  it('зажимает числа и отбрасывает лишние ключи', () => {
    const template = parseSceneObjectTemplate({
      sceneObject: {
        version: 1,
        kind: 'decor',
        resolution: 0,
        scale: -5,
        speed: 1_000_000,
        rotation: 400,
        footprint: { shape: 'circle', radius: 10 },
        silhouette: { shape: 'circle', radius: 10, bottom: -3 },
        somethingElse: true,
      },
    });

    expect(template?.resolution).toBe(1);
    expect(template?.scale).toBe(1);
    expect(template?.speed).toBe(100000);
    expect(template?.rotation).toBe(359);
    expect(template?.silhouette.bottom).toBe(0);
    expect(template).not.toHaveProperty('somethingElse');
  });
});
