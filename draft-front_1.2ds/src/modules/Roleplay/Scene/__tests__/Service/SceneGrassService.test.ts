import { describe, expect, it } from 'vitest';
import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SCENE_GRASS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_GRASS';
import { SCENE_TERRAIN_BLEND } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TERRAIN_BLEND';
import { SceneGeometryService } from '@/modules/Roleplay/Scene/Service/SceneGeometryService';
import { SceneGrassService } from '@/modules/Roleplay/Scene/Service/SceneGrassService';
import { SceneGrassTextureService } from '@/modules/Roleplay/Scene/Service/SceneGrassTextureService';
import { SceneTerrainPatternService } from '@/modules/Roleplay/Scene/Service/SceneTerrainPatternService';
import { SceneTerrainTextureService } from '@/modules/Roleplay/Scene/Service/SceneTerrainTextureService';

const board: SceneBounds = { x: 0, y: 0, width: 1000, height: 1000 };

const element = (
  id: number,
  shape: SceneShape,
  props: SceneElement['props'],
  kind: SceneElement['kind'] = 'terrain',
): SceneElement => ({
  id,
  boardId: 1,
  kind,
  shape,
  props,
  sortOrder: id,
  targetBoardId: null,
  asset: null,
});

const grass = (): SceneElement => element(1, { type: 'rect', x: 0, y: 0, w: 1000, h: 1000 }, { kind: 'grass' });

const spill = (resolution: number): number => Math.max(1, resolution) * SCENE_TERRAIN_BLEND.spillIpari;

describe('SceneGrassService', () => {
  const geometry = new SceneGeometryService();
  const service = new SceneGrassService(
    geometry,
    new SceneGrassTextureService(new SceneTerrainTextureService(), new SceneTerrainPatternService()),
  );

  it('раскладывает высокие пучки по траве', () => {
    const tufts = service.placeClumps([grass()], board, 64);

    expect(tufts.length).toBeGreaterThan(50);
    for (const tuft of tufts) {
      expect(geometry.contains(grass().shape, [tuft.x, tuft.y], 0)).toBe(true);
    }
  });

  it('сеет ковёр заметно плотнее высоких пучков', () => {
    const carpet = service.placeCarpet([grass()], board, 64);
    const clumps = service.placeClumps([grass()], board, 64);

    expect(carpet.length).toBeGreaterThan(clumps.length * 5);
    expect(carpet[0].height).toBeLessThan(clumps[0].height);
  });

  it('повторяет раскладку от вызова к вызову', () => {
    const first = service.placeCarpet([grass()], board, 64);
    const second = service.placeCarpet([grass()], board, 64);

    expect(second.map((tuft) => [tuft.x, tuft.y, tuft.variant])).toEqual(
      first.map((tuft) => [tuft.x, tuft.y, tuft.variant]),
    );
  });

  it('не сажает траву на песок, нарисованный позже', () => {
    const sand = element(2, { type: 'rect', x: 400, y: 400, w: 200, h: 200 }, { kind: 'sand' });
    const tufts = service.placeCarpet([grass(), sand], board, 64);
    const margin = spill(64);

    expect(tufts.length).toBeGreaterThan(100);
    for (const tuft of tufts) {
      const insideSand =
        tuft.x >= 400 - margin && tuft.x <= 600 + margin && tuft.y >= 400 - margin && tuft.y <= 600 + margin;
      expect(insideSand).toBe(false);
    }
  });

  it('не сеет траву на настиле моста', () => {
    const bridge = element(2, { type: 'rect', x: 400, y: 400, w: 200, h: 200 }, { kind: 'bridge' }, 'support');
    const tufts = service.placeCarpet([grass(), bridge], board, 64);
    const margin = spill(64);

    expect(tufts.length).toBeGreaterThan(100);
    for (const tuft of tufts) {
      const insideBridge =
        tuft.x >= 400 - margin && tuft.x <= 600 + margin && tuft.y >= 400 - margin && tuft.y <= 600 + margin;
      expect(insideBridge).toBe(false);
    }
  });

  it('оставляет запас на кромку и осыпь воды', () => {
    const water = element(
      2,
      { type: 'rect', x: 300, y: 300, w: 400, h: 400 },
      { kind: 'water', shoreKind: 'sand', shoreWidth: 100 },
    );
    const tufts = service.placeCarpet([grass(), water], board, 64);
    const margin = 100 + spill(64);

    for (const tuft of tufts) {
      const insideWater =
        tuft.x >= 300 - margin && tuft.x <= 700 + margin && tuft.y >= 300 - margin && tuft.y <= 700 + margin;
      expect(insideWater).toBe(false);
    }
  });

  it('сажает траву на остров поверх воды', () => {
    const lake = element(2, { type: 'rect', x: 200, y: 200, w: 600, h: 600 }, { kind: 'water' });
    const island = element(3, { type: 'rect', x: 350, y: 350, w: 300, h: 300 }, { kind: 'grass', overWater: true });
    const tufts = service.placeCarpet([grass(), lake, island], board, 64);
    const onIsland = tufts.filter((tuft) => tuft.x > 360 && tuft.x < 640 && tuft.y > 360 && tuft.y < 640);

    expect(onIsland.length).toBeGreaterThan(0);
  });

  it('не сажает траву в русло реки и на его кромку', () => {
    const river = element(
      2,
      {
        type: 'path',
        points: [
          [100, 500],
          [900, 500],
        ],
        widths: [60, 60],
      },
      { kind: 'water', shoreKind: 'sand', shoreWidth: 30 },
    );
    const tufts = service.placeCarpet([grass(), river], board, 64);

    expect(tufts.length).toBeGreaterThan(100);
    for (const tuft of tufts) {
      if (tuft.x < 150 || tuft.x > 850) {
        continue;
      }

      expect(Math.abs(tuft.y - 500)).toBeGreaterThan(80);
    }
  });

  it('переиспользует раскладку и сбрасывает её при смене террейна', () => {
    const first = service.placeCarpet([grass()], board, 64);
    const repeat = service.placeCarpet([grass()], board, 64);

    expect(repeat).toBe(first);

    const sand = element(2, { type: 'rect', x: 400, y: 400, w: 200, h: 200 }, { kind: 'sand' });
    const changed = service.placeCarpet([grass(), sand], board, 64);

    expect(changed).not.toBe(first);
    expect(changed.length).toBeLessThan(first.length);
  });

  it('режет ковёр на полосы по нижней границе', () => {
    const tuft = (y: number) => ({
      x: 0,
      y,
      variant: 0,
      height: 1,
      flip: false,
      rotation: 0,
      tint: 0xffffff,
      alpha: 1,
      sequence: y,
    });
    const bands = service.band([tuft(0), tuft(10), tuft(33), tuft(70)], 64);

    expect(bands.map((band) => band.y)).toEqual([32, 64, 96]);
    expect(bands.map((band) => band.tufts.length)).toEqual([2, 1, 1]);
  });

  it('держит потолок числа пучков', () => {
    const big: SceneBounds = { x: 0, y: 0, width: 6000, height: 6000 };
    const field = element(1, { type: 'rect', x: 0, y: 0, w: 6000, h: 6000 }, { kind: 'grass' });
    const tufts = service.placeClumps([field], big, 64);

    expect(tufts.length).toBe(SCENE_GRASS.clumpLimit);
  });
});
