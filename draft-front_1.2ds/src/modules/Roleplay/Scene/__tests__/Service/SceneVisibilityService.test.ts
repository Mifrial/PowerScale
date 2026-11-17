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
  element(id, 'obstacle', { type: 'rect', x: 80, y, w: 16, h }, { blocksMovement: true, blocksVision: true });

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

  it('игнорирует крону, которая начинается выше глаз', () => {
    const large: SceneBoard = { ...board, boundWidth: 640, boundHeight: 640 };
    const tree = (sightBottom: number): SceneElement =>
      element(
        2,
        'decor',
        { type: 'point', x: 320, y: 320 },
        { scale: 420, baseRadius: 10, sightRadius: 45, sightBottom, label: 'Дерево' },
      );

    const crown = surfaces.build(
      large,
      [element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 640, h: 640 }, { kind: 'grass' }), tree(85)],
      64,
    );
    expect(service.sees(crown, [370, 560], [370, 80])).toBe(true);

    // Низкая крона пересекает луч и закрывает цель.
    const low = surfaces.build(
      large,
      [element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 640, h: 640 }, { kind: 'grass' }), tree(10)],
      64,
    );
    expect(service.sees(low, [370, 560], [370, 80])).toBe(false);
  });

  it('настил не пускает луч сквозь себя, но не мешает смотреть с него и из-под него', () => {
    const large: SceneBoard = { ...board, boundWidth: 640, boundHeight: 640 };
    const deck = (elevation: number): SceneElement =>
      element(2, 'support', { type: 'rect', x: 240, y: 0, w: 160, h: 640 }, { kind: 'bridge', elevation, height: 16 });
    const grid = surfaces.build(
      large,
      [element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 640, h: 640 }, { kind: 'grass' }), deck(100)],
      64,
    );

    // На настиле и под настилом видно вдоль своего уровня.
    expect(service.sees(grid, [320, 560], [320, 80], undefined, undefined, 1, 1)).toBe(true);
    expect(service.sees(grid, [320, 560], [320, 80], undefined, undefined, 0, 0)).toBe(true);

    // Сквозь настил — нет: ни снизу вверх, ни сверху вниз.
    expect(service.sees(grid, [320, 560], [320, 80], undefined, undefined, 1, 0)).toBe(false);
    expect(service.sees(grid, [320, 560], [320, 80], undefined, undefined, 0, 1)).toBe(false);

    // Настил на уровне пола ничего не перекрывает.
    const flat = surfaces.build(
      large,
      [element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 640, h: 640 }, { kind: 'grass' }), deck(0)],
      64,
    );
    expect(service.sees(flat, [320, 560], [320, 80], undefined, undefined, 1, 0)).toBe(true);
  });

  it('пропускает луч под настилом, который выше глаз', () => {
    const large: SceneBoard = { ...board, boundWidth: 640, boundHeight: 640 };
    const grid = surfaces.build(
      large,
      [
        element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 640, h: 640 }, { kind: 'grass' }),
        element(
          2,
          'support',
          { type: 'rect', x: 240, y: 0, w: 160, h: 640 },
          { kind: 'bridge', elevation: 100, height: 16 },
        ),
      ],
      64,
    );

    // Глаза на 1.078 ниже низа полосы 1.3125 — луч идёт под путепроводом.
    expect(service.sees(grid, [80, 320], [560, 320])).toBe(true);
    // С берега видно голову стоящего на настиле: луч к голове проходит над кромкой.
    expect(service.sees(grid, [80, 320], [320, 320], undefined, undefined, 0, 1)).toBe(true);
  });

  it('не скрывает цель за низким перекрытием: голова остаётся видна', () => {
    const large: SceneBoard = { ...board, boundWidth: 640, boundHeight: 640 };
    const grid = surfaces.build(
      large,
      [
        element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 640, h: 640 }, { kind: 'grass' }),
        element(
          2,
          'support',
          { type: 'rect', x: 240, y: 0, w: 160, h: 640 },
          { kind: 'bridge', elevation: 70, height: 16 },
        ),
      ],
      64,
    );

    // Полоса 0.844…1.094 закрывает грудь, но голова выше неё.
    expect(service.sees(grid, [80, 320], [560, 320])).toBe(true);
  });

  it('под мостом на врезанном русле видно вдоль воды, но не на настил', () => {
    const large: SceneBoard = { ...board, boundWidth: 640, boundHeight: 640 };
    const grid = surfaces.build(
      large,
      [
        element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 640, h: 640 }, { kind: 'grass' }),
        element(2, 'terrain', { type: 'rect', x: 240, y: 0, w: 160, h: 640 }, { kind: 'water', depth: 96 }),
        element(
          3,
          'support',
          { type: 'rect', x: 240, y: 240, w: 160, h: 160 },
          { kind: 'bridge', elevation: 0, height: 16 },
        ),
      ],
      64,
    );

    // Оба под настилом: луч идёт вдоль воды под досками.
    expect(service.sees(grid, [320, 300], [320, 380], undefined, undefined, 0, 0)).toBe(true);
    // Сквозь доски — ни снизу вверх, ни сверху вниз.
    expect(service.sees(grid, [320, 300], [320, 320], undefined, undefined, 0, 1)).toBe(false);
    expect(service.sees(grid, [320, 300], [320, 320], undefined, undefined, 1, 0)).toBe(false);
  });

  it('не скрывает за низким забором, но скрывает за стеной выше глаз', () => {
    const build = (height?: number) =>
      surfaces.build(
        board,
        [
          grass(),
          element(
            2,
            'obstacle',
            { type: 'rect', x: 80, y: 0, w: 16, h: 256 },
            { height, blocksMovement: true, blocksVision: true },
          ),
        ],
        64,
      );

    // Забор 48 (0.75 ипари) ниже глаз: двор за ним виден.
    const low = build(48);
    expect(service.map(low, [40, 128]).visible[cellOf(low, 12, 8)]).toBe(1);
    // Стена 128 (2 ипари) выше глаз: клетка за ней скрыта.
    const tall = build(128);
    expect(service.map(tall, [40, 128]).visible[cellOf(tall, 12, 8)]).toBe(0);
  });

  it('не протекает вбок от двери и не выпускает сквозь глухую стену', () => {
    const grid = surfaces.build(
      board,
      [
        grass(),
        element(
          2,
          'obstacle',
          {
            type: 'wall',
            width: 8,
            points: [
              [64, 64],
              [192, 64],
              [192, 192],
              [64, 192],
              [64, 64],
            ],
          },
          { height: 128, blocksMovement: true, blocksVision: true },
        ),
        element(
          3,
          'opening',
          {
            type: 'wall',
            width: 8,
            points: [
              [112, 192],
              [144, 192],
            ],
          },
          { kind: 'door', state: 'open' },
        ),
      ],
      64,
    );

    const map = service.map(grid, [128, 120], 256);
    const at = (x: number, y: number): number => cellOf(grid, Math.floor(x / 16), Math.floor(y / 16));

    // Прямо в дверь видно, сбоку от проёма — нет: тень не размывается конусом.
    expect(map.visible[at(128, 230)]).toBe(1);
    expect(map.visible[at(88, 208)]).toBe(0);
    expect(map.visible[at(40, 230)]).toBe(0);
    expect(map.visible[at(200, 230)]).toBe(0);
  });

  it('на проходимой крыше стены своего этажа не прячут настил', () => {
    const grid = surfaces.build(
      board,
      [
        grass(),
        element(
          2,
          'support',
          { type: 'rect', x: 48, y: 48, w: 160, h: 160 },
          { kind: 'roof', walkable: true, elevation: 128, height: 8 },
        ),
        element(
          3,
          'obstacle',
          {
            type: 'wall',
            width: 8,
            points: [
              [48, 128],
              [208, 128],
            ],
          },
          { height: 128, blocksMovement: true, blocksVision: true },
        ),
      ],
      64,
    );

    // Изнутри дома стена выше глаз закрывает настил за ней.
    const inside = service.map(grid, [128, 100], 256, undefined, undefined, 0);
    expect(inside.visible[cellOf(grid, 8, 12)]).toBe(0);
    // С крыши глаза выше верха стены: весь настил виден.
    const roof = service.map(grid, [128, 100], 256, undefined, undefined, 1);
    expect(roof.visible[cellOf(grid, 8, 12)]).toBe(1);
  });

  it('без радиуса доходит до дальнего угла поля', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const visible = service.map(grid, [8, 8]);

    expect(visible.visible[cellOf(grid, 15, 15)]).toBe(1);
  });

  it('ограничивает обзор кругом, а не квадратом', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const visible = service.map(grid, [128, 128], 32);

    expect(visible.visible[cellOf(grid, 8, 8)]).toBe(1);
    expect(visible.visible[cellOf(grid, 9, 8)]).toBe(1);
    expect(visible.visible[cellOf(grid, 12, 8)]).toBe(0);
    // Угол квадрата дальше радиуса: по диагонали клетка (10, 10) не видна.
    expect(visible.visible[cellOf(grid, 10, 10)]).toBe(0);
  });

  it('не видит ничего из точки вне поля', () => {
    const grid = surfaces.build(board, [grass()], 64);
    const visible = service.map(grid, [-10, 128]);

    expect(visible.visible.some((value) => value === 1)).toBe(false);
    expect(service.sees(grid, [-10, 128], [128, 128])).toBe(false);
  });
});
