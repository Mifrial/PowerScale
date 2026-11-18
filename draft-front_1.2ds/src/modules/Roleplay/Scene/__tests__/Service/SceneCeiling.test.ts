import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import { SCENE_FLOOR } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_FLOOR';
import { sceneFloorCeilingTop, sceneWallCeiling, sceneWallTop } from '@/modules/Roleplay/Scene/Service/SceneCeiling';

const floor = (ceiling?: number): SceneElement => ({
  id: 1,
  boardId: 1,
  kind: 'terrain',
  shape: { type: 'rect', x: 0, y: 0, w: 500, h: 500 },
  props: { kind: 'wood', ceiling },
  sortOrder: 0,
  targetBoardId: null,
  asset: null,
});

const wall = (height: number): SceneElement => ({
  id: 2,
  boardId: 1,
  kind: 'obstacle',
  shape: {
    type: 'wall',
    width: 16,
    points: [
      [500, 0],
      [500, 500],
    ],
  },
  props: { blocksMovement: true, blocksVision: true, height },
  sortOrder: 1,
  targetBoardId: null,
  asset: null,
});

describe('sceneFloorCeilingTop', () => {
  it('без поля потолка берёт высоту этажа', () => {
    expect(sceneFloorCeilingTop(floor(undefined), 64)).toBe(SCENE_FLOOR.storeyIpari * 64);
  });

  it('явный ноль — открытое небо', () => {
    expect(sceneFloorCeilingTop(floor(0), 64)).toBeNull();
  });

  it('явная высота считается от пола', () => {
    expect(sceneFloorCeilingTop(floor(160), 64)).toBe(160);
  });
});

describe('sceneWallTop', () => {
  it('на полу берёт потолок пола, а не высоту стены', () => {
    const walls = [floor(160), wall(48)];

    expect(sceneWallCeiling(walls[1], walls, 64)).toBe(160);
    expect(sceneWallTop(walls[1], walls, 64)).toBe(160);
  });

  it('без пола остаётся собственная высота стены', () => {
    const wallOnly = wall(48);

    expect(sceneWallCeiling(wallOnly, [wallOnly], 64)).toBeNull();
    expect(sceneWallTop(wallOnly, [wallOnly], 64)).toBe(48);
  });
});
