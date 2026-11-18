import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import { SCENE_FURNITURE_OPTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_FURNITURE';
import { SceneFurnitureService } from '@/modules/Roleplay/Scene/Service/SceneFurnitureService';

const furniture = (kind: string, rotation = 0): SceneElement => ({
  id: 1,
  boardId: 1,
  kind: 'obstacle',
  shape: { type: 'rect', x: 100, y: 100, w: 96, h: 64 },
  props: { kind, height: 51, material: 'wood', rotation },
  sortOrder: 0,
  targetBoardId: null,
  asset: null,
});

describe('SceneFurnitureService', () => {
  const service = new SceneFurnitureService();

  it('рисует каждый предмет набора', () => {
    for (const option of SCENE_FURNITURE_OPTIONS) {
      const graphics = service.paint(furniture(option.value));

      expect(graphics, option.value).not.toBeNull();
      expect(graphics?.context.instructions.length, option.value).toBeGreaterThan(0);
    }
  });

  it('поворачивает предмет вокруг центра', () => {
    const graphics = service.paint(furniture('table', 90));

    expect(graphics?.rotation).toBeCloseTo(Math.PI / 2);
    expect(graphics?.pivot.x).toBe(148);
    expect(graphics?.pivot.y).toBe(132);
  });

  it('не рисует обычное препятствие и неизвестный подтип', () => {
    expect(service.paint(furniture(''))).toBeNull();
    expect(service.paint(furniture('throne'))).toBeNull();
  });
});
