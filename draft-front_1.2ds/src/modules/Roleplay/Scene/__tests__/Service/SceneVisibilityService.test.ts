import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SceneSurfaceService } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';
import { SceneVisibilityService } from '@/modules/Roleplay/Scene/Service/SceneVisibilityService';

const board: SceneBoard = {
  id: 1,
  name: 'Поле',
  sortOrder: 0,
  baseFile: null,
  boundWidth: 256,
  boundHeight: 256,
  occupancyCell: 16,
  zQuantum: 16,
  gridStep: 32,
};

const element = (
  id: number,
  kind: SceneElementKind,
  shape: SceneShape,
  props: SceneElementProps = {},
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

const grass = (): SceneElement =>
  element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 256, h: 256 }, { kind: 'grass' });

const wall = (id: number, y: number, h: number): SceneElement =>
  element(
    id,
    'obstacle',
    { type: 'rect', x: 80, y, w: 16, h },
    { blocksMovement: true, blocksVision: true },
  );

const cellOf = (grid: ReturnType<SceneSurfaceService['build']>, column: number, row: number): number =>
  row * grid.columns + column;

describe('SceneVisibilityService', () => {
  const surfaces = new SceneSurfaceService();
  const service = new SceneVisibilityService();

  it('скрывает клетки за стеной и показывает самую стену', () => {
    const grid = surfaces.build(board, [grass(), wall(2, 0, 256)], 64);
    const visible = service.map(grid, [40, 128]);

    expect(visible.columns).toBe(16);
    expect(visible.visible[cellOf(grid, 4, 8)]).toBe(1);
    expect(visible.visible[cellOf(grid, 5, 8)]).toBe(1);
    expect(visible.visible[cellOf(grid, 12, 8)]).toBe(0);
  });

  it('пропускает луч через проём в стене', () => {
    const grid = surfaces.build(board, [grass(), wall(2, 0, 96), wall(3, 160, 96)], 64);
    const visible = service.map(grid, [40, 128]);

    expect(visible.visible[cellOf(grid, 12, 8)]).toBe(1);
    expect(service.sees(grid, [40, 128], [200, 128])).toBe(true);
  });

  it('не видит точку сквозь стену, но видит в обход', () => {
    const grid = surfaces.build(board, [grass(), wall(2, 0, 224)], 64);

    expect(service.sees(grid, [40, 128], [200, 128])).toBe(false);
    expect(service.sees(grid, [40, 240], [200, 240])).toBe(true);
  });

  it('скрывает клетку за валуном и открывает её сбоку', () => {
    const large: SceneBoard = { ...board, boundWidth: 640, boundHeight: 640 };
    const grid = surfaces.build(
      large,
      [
        element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 640, h: 640 }, { kind: 'grass' }),
        element(2, 'decor', { type: 'point', x: 320, y: 320 }, { scale: 420, label: 'Валун' }),
      ],
      64,
    );

    expect(service.sees(grid, [320, 560], [320, 80])).toBe(false);
    expect(service.sees(grid, [80, 80], [240, 80])).toBe(true);
  });

  it('ограничивает обзор радиусом', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const visible = service.map(grid, [128, 128], 32);

    expect(visible.visible[cellOf(grid, 8, 8)]).toBe(1);
    expect(visible.visible[cellOf(grid, 9, 8)]).toBe(1);
    expect(visible.visible[cellOf(grid, 12, 8)]).toBe(0);
  });

  it('не видит ничего из точки вне поля', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const visible = service.map(grid, [-10, 128]);

    expect(visible.visible.some((value) => value === 1)).toBe(false);
    expect(service.sees(grid, [-10, 128], [128, 128])).toBe(false);
  });
});
