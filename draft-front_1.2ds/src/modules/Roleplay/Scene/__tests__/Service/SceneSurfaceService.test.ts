import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import {
  SceneSurfaceService,
  surfaceCenter,
  surfaceIndex,
  surfaceUnderDeck,
} from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';

const board = (overrides: Partial<SceneBoard> = {}): SceneBoard => ({
  id: 1,
  name: 'Поле',
  sortOrder: 0,
  baseFile: null,
  boundWidth: 256,
  boundHeight: 256,
  occupancyCell: 16,
  zQuantum: 16,
  gridStep: 32,
  ...overrides,
});

const element = (
  id: number,
  kind: SceneElementKind,
  shape: SceneShape,
  props: SceneElementProps = {},
  sortOrder = id,
): SceneElement => ({
  id,
  boardId: 1,
  kind,
  shape,
  props,
  sortOrder,
  targetBoardId: null,
  asset: null,
});

const grass = (): SceneElement =>
  element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 256, h: 256 }, { kind: 'grass' }, 0);

const cellOf = (grid: ReturnType<SceneSurfaceService['build']>, column: number, row: number): number =>
  row * grid.columns + column;

describe('SceneSurfaceService', () => {
  const service = new SceneSurfaceService();

  it('ставит скорость по террейну и считает стоимость', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 0, y: 0, w: 64, h: 256 }, { kind: 'dirt' }, 10),
        element(3, 'terrain', { type: 'rect', x: 192, y: 0, w: 64, h: 256 }, { kind: 'water' }, 20),
      ],
      64,
    );

    expect(grid.kinds[cellOf(grid, 0, 0)]).toBe('dirt');
    expect(grid.speeds[cellOf(grid, 0, 0)]).toBe(150);
    expect(grid.costs[cellOf(grid, 0, 0)]).toBeCloseTo(2 / 3);
    expect(grid.kinds[cellOf(grid, 6, 0)]).toBe('grass');
    expect(grid.costs[cellOf(grid, 6, 0)]).toBe(1);
    expect(grid.kinds[cellOf(grid, 13, 0)]).toBe('water');
    expect(grid.speeds[cellOf(grid, 13, 0)]).toBe(20);
    expect(grid.costs[cellOf(grid, 13, 0)]).toBe(5);
  });

  it('вода перекрывает дорогу независимо от sortOrder', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 0, y: 0, w: 256, h: 256 }, { kind: 'dirt' }, 100),
        element(3, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water' }, -10),
      ],
      64,
    );

    expect(grid.kinds[cellOf(grid, 8, 0)]).toBe('water');
    expect(grid.costs[cellOf(grid, 8, 0)]).toBe(5);
  });

  it('остров перекрывает воду независимо от sortOrder', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water' }, -10),
        element(3, 'terrain', { type: 'rect', x: 96, y: 96, w: 64, h: 64 }, { kind: 'grass', overWater: true }, 100),
      ],
      64,
    );

    expect(grid.kinds[cellOf(grid, 8, 8)]).toBe('grass');
    expect(grid.costs[cellOf(grid, 8, 8)]).toBe(1);
    expect(grid.kinds[cellOf(grid, 8, 2)]).toBe('water');
  });

  it('мост возвращает проходимость над водой', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water' }, 10),
        element(3, 'support', { type: 'rect', x: 96, y: 96, w: 64, h: 64 }, { kind: 'bridge' }, 20),
      ],
      64,
    );

    expect(grid.kinds[cellOf(grid, 8, 8)]).toBe('bridge');
    expect(grid.speeds[cellOf(grid, 8, 8)]).toBe(150);
    expect(grid.sightBottom[cellOf(grid, 8, 8)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('проходимая крыша ложится настилом над полом, обычная — потолком', () => {
    const floor = element(2, 'terrain', { type: 'rect', x: 64, y: 64, w: 128, h: 128 }, { kind: 'dirt' }, 10);
    const walkable = element(
      3,
      'support',
      { type: 'rect', x: 48, y: 48, w: 160, h: 160 },
      { kind: 'roof', elevation: 128, height: 16, walkable: true },
      20,
    );
    const ceiling = element(
      3,
      'support',
      { type: 'rect', x: 48, y: 48, w: 160, h: 160 },
      { kind: 'roof', elevation: 128, height: 16 },
      20,
    );

    const grid = service.build(board(), [grass(), floor, walkable], 64);
    const cell = cellOf(grid, 8, 8);
    expect(grid.kinds[cell]).toBe('roof');
    expect(grid.speeds[cell]).toBe(100);
    expect(grid.elevation[cell]).toBe(128);
    // Пол под крышей остаётся полом: интерьер не заменился настилом.
    expect(grid.ground[cell]).toBe(0);
    expect(grid.clearance[cell]).toBe(112);

    // Обычная крыша поверхности не даёт: под ней пол, а не крыша.
    const ceilingGrid = service.build(board(), [grass(), floor, ceiling], 64);
    expect(ceilingGrid.kinds[cell]).toBe('dirt');
    expect(ceilingGrid.elevation[cell]).toBe(0);
    expect(ceilingGrid.clearance[cell]).toBe(Number.POSITIVE_INFINITY);
  });

  it('прорезает обзор в крыше там, где на неё выходит марш', () => {
    const roof = element(
      2,
      'support',
      { type: 'rect', x: 48, y: 48, w: 160, h: 160 },
      { kind: 'roof', elevation: 128, height: 16, speed: 100, walkable: true },
      20,
    );
    const stairs = element(
      3,
      'support',
      { type: 'rect', x: 96, y: 96, w: 96, h: 48 },
      { kind: 'stairs', elevation: 128, height: 16, speed: 100 },
      25,
    );
    const grid = service.build(board(), [grass(), roof, stairs], 64);
    const hatch = cellOf(grid, 8, 8);
    const covered = cellOf(grid, 4, 4);

    // Над маршем крыша не перекрывает взгляд: виден проход вниз.
    expect(grid.sightTop[hatch]).toBe(Number.NEGATIVE_INFINITY);
    expect(grid.sightBottom[hatch]).toBe(Number.POSITIVE_INFINITY);
    // Рядом крыша остаётся потолком.
    expect(grid.sightTop[covered]).toBe(128 / 64);
    expect(grid.sightBottom[covered]).toBeCloseTo(112 / 64);
  });

  it('настил на уровне берега перекрывает толщину над врезанным руслом', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water', depth: 96 }, 10),
        element(
          3,
          'support',
          { type: 'rect', x: 96, y: 96, w: 64, h: 64 },
          { kind: 'bridge', elevation: 0, height: 16 },
          20,
        ),
      ],
      64,
    );
    const cell = cellOf(grid, 8, 8);

    // Пол под мостом — вода, а полоса настила лежит ниже нуля поля.
    expect(grid.ground[cell]).toBe(-96);
    expect(grid.elevation[cell]).toBe(0);
    expect(grid.sightBottom[cell]).toBeCloseTo(-16 / 64);
    expect(grid.sightTop[cell]).toBe(0);
  });

  it('считает высоту настила и зазор под ним', () => {
    const high = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water' }, 10),
        element(
          3,
          'support',
          { type: 'rect', x: 96, y: 96, w: 64, h: 64 },
          { kind: 'bridge', elevation: 100, height: 16 },
          20,
        ),
      ],
      64,
    );
    const low = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water' }, 10),
        element(
          3,
          'support',
          { type: 'rect', x: 96, y: 96, w: 64, h: 64 },
          { kind: 'bridge', elevation: 0, height: 16 },
          20,
        ),
      ],
      64,
    );

    expect(high.elevation[cellOf(high, 8, 8)]).toBe(100);
    expect(high.clearance[cellOf(high, 8, 8)]).toBe(84);
    expect(low.elevation[cellOf(low, 8, 8)]).toBe(0);
    expect(low.clearance[cellOf(low, 8, 8)]).toBe(-16);
    // Вне настила потолка нет: зазор — открытое небо.
    expect(high.clearance[cellOf(high, 0, 0)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('настил перекрывает только свою толщину, а пол под ним остаётся прежним', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water', depth: 64 }, 10),
        element(
          3,
          'support',
          { type: 'rect', x: 96, y: 96, w: 64, h: 64 },
          { kind: 'bridge', elevation: 100, height: 16 },
          20,
        ),
      ],
      64,
    );
    const cell = cellOf(grid, 8, 8);

    // Низ и верх настила в ипари: сквозь эту полосу не видно, выше и ниже — видно.
    expect(grid.sightBottom[cell]).toBeCloseTo(84 / 64);
    expect(grid.sightTop[cell]).toBeCloseTo(100 / 64);
    // Пол под настилом — вода, а не настил: обзор снизу идёт от неё.
    expect(grid.ground[cell]).toBe(-64);
    expect(grid.elevation[cell]).toBe(100);
    // Вне настила полоса пуста: низ — небо, верх — ниже нуля.
    expect(grid.sightBottom[cellOf(grid, 0, 0)]).toBe(Number.POSITIVE_INFINITY);
    expect(grid.sightTop[cellOf(grid, 0, 0)]).toBe(Number.NEGATIVE_INFINITY);
  });

  it('держит потолок силуэта декора', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'decor',
          { type: 'point', x: 128, y: 128 },
          {
            footprint: { shape: 'circle', radius: 10 },
            silhouette: { shape: 'circle', radius: 24, bottom: 0, top: 48 },
          },
        ),
      ],
      64,
    );

    // Камень перекрывает от земли до своей высоты, но не до неба.
    expect(grid.sightBottom[cellOf(grid, 8, 8)]).toBe(0);
    expect(grid.sightTop[cellOf(grid, 8, 8)]).toBeCloseTo(48 / 64);
  });

  it('опускает воду на глубину и делает берег наклонным', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'terrain',
          { type: 'rect', x: 96, y: 0, w: 64, h: 256 },
          { kind: 'water', depth: 32, shoreWidth: 48 },
          10,
        ),
      ],
      64,
    );

    // Вода стоит на своей глубине.
    expect(grid.elevation[cellOf(grid, 8, 0)]).toBe(-32);
    // Берег у уреза опущен почти до воды, дальше поднимается к отметке поля.
    const near = grid.elevation[cellOf(grid, 5, 0)];
    const far = grid.elevation[cellOf(grid, 3, 0)];
    expect(near).toBeLessThan(0);
    expect(near).toBeLessThan(far);
    expect(far).toBeLessThan(0);
    // За пределами береговой полосы высота прежняя.
    expect(grid.elevation[cellOf(grid, 0, 0)]).toBe(0);
  });

  it('без береговой полосы вода остаётся с обрывом у уреза', () => {
    const grid = service.build(
      board(),
      [grass(), element(2, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water', depth: 32 }, 10)],
      64,
    );

    expect(grid.elevation[cellOf(grid, 8, 0)]).toBe(-32);
    expect(grid.elevation[cellOf(grid, 5, 0)]).toBe(0);
  });

  it('под настилом над врезанной водой появляется зазор', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water', depth: 32 }, 10),
        element(
          3,
          'support',
          { type: 'rect', x: 96, y: 96, w: 64, h: 64 },
          { kind: 'bridge', elevation: 0, height: 16 },
          20,
        ),
      ],
      64,
    );

    expect(grid.clearance[cellOf(grid, 8, 8)]).toBe(16);
  });

  it('считает «под настилом» только при конечном положительном зазоре', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'support',
          { type: 'rect', x: 96, y: 96, w: 64, h: 64 },
          { kind: 'bridge', elevation: 100, height: 16 },
          20,
        ),
        element(
          3,
          'support',
          { type: 'rect', x: 0, y: 0, w: 64, h: 64 },
          { kind: 'bridge', elevation: 0, height: 16 },
          30,
        ),
      ],
      64,
    );

    // Под высоким настилом зазор конечный и положительный.
    expect(surfaceUnderDeck(grid, 136, 136)).toBe(true);
    // Настил-пол: зазор отрицательный, это не потолок.
    expect(surfaceUnderDeck(grid, 40, 40)).toBe(false);
    // Открытая трава: потолка нет вовсе.
    expect(surfaceUnderDeck(grid, 200, 200)).toBe(false);
  });

  it('поворачивает настил моста вместе с опорой', () => {
    const shape: SceneShape = { type: 'rect', x: 93, y: 84, w: 70, h: 40 };
    const straight = service.build(board(), [grass(), element(2, 'support', shape, { kind: 'bridge' }, 20)], 64);
    const rotated = service.build(
      board(),
      [grass(), element(2, 'support', shape, { kind: 'bridge', rotation: 90 }, 20)],
      64,
    );

    // Клетка (8,4) попадает в повёрнутый настил, но не в осевой.
    expect(straight.kinds[cellOf(straight, 8, 4)]).toBe('grass');
    expect(rotated.kinds[cellOf(rotated, 8, 4)]).toBe('bridge');
  });

  it('съезд считается настилом, как мост', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'terrain', { type: 'rect', x: 96, y: 0, w: 64, h: 256 }, { kind: 'water' }, 10),
        element(3, 'support', { type: 'rect', x: 96, y: 96, w: 64, h: 64 }, { kind: 'ramp' }, 20),
      ],
      64,
    );

    expect(grid.kinds[cellOf(grid, 8, 8)]).toBe('bridge');
    expect(grid.speeds[cellOf(grid, 8, 8)]).toBe(150);
  });

  it('делает препятствие непроходимым и непрозрачным по флагам', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'obstacle',
          { type: 'rect', x: 64, y: 64, w: 32, h: 32 },
          { blocksMovement: true, blocksVision: true },
        ),
      ],
      64,
    );

    expect(grid.speeds[cellOf(grid, 4, 4)]).toBe(0);
    expect(grid.costs[cellOf(grid, 4, 4)]).toBe(Number.POSITIVE_INFINITY);
    expect(grid.sightBottom[cellOf(grid, 4, 4)]).toBe(0);
    expect(grid.speeds[cellOf(grid, 0, 0)]).toBe(100);

    const transparent = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'obstacle',
          { type: 'rect', x: 64, y: 64, w: 32, h: 32 },
          { blocksMovement: true, blocksVision: false },
        ),
      ],
      64,
    );

    expect(transparent.speeds[cellOf(transparent, 4, 4)]).toBe(0);
    expect(transparent.sightBottom[cellOf(transparent, 4, 4)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('пишет высоту препятствия в полосу обзора', () => {
    const low = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'obstacle',
          { type: 'rect', x: 64, y: 64, w: 32, h: 32 },
          { height: 48, blocksMovement: true, blocksVision: true },
        ),
      ],
      64,
    );
    const lowCell = cellOf(low, 4, 4);
    expect(low.sightBottom[lowCell]).toBe(0);
    expect(low.sightTop[lowCell]).toBeCloseTo(0.75);

    // Без высоты стена по-прежнему до неба: старые сцены не меняются.
    const tall = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'obstacle',
          { type: 'rect', x: 64, y: 64, w: 32, h: 32 },
          { blocksMovement: true, blocksVision: true },
        ),
      ],
      64,
    );
    expect(tall.sightTop[cellOf(tall, 4, 4)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('тонкая стена попадает в сетку, а проём в ней не закрывается', () => {
    const wallShape: SceneShape = {
      type: 'wall',
      points: [
        [32, 128],
        [224, 128],
      ],
      width: 8,
    };
    const gateShape: SceneShape = {
      type: 'wall',
      points: [
        [112, 128],
        [144, 128],
      ],
      width: 8,
    };
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'obstacle', wallShape, { height: 128, blocksMovement: true, blocksVision: true }),
        element(3, 'opening', gateShape, { kind: 'gate', state: 'open' }),
      ],
      64,
    );

    // Забор толщиной 8 при клетке 16: центры клеток в 8 единицах от осевой,
    // без допуска в полклетки он исчезал бы из сетки целиком.
    expect(grid.speeds[cellOf(grid, 5, 8)]).toBe(0);
    expect(grid.sightBottom[cellOf(grid, 5, 8)]).toBe(0);
    // Калитка вырезается тем же допуском: проход открыт.
    expect(grid.speeds[cellOf(grid, 8, 8)]).toBe(100);
    expect(grid.sightBottom[cellOf(grid, 8, 8)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('декор перекрывает ход основанием и обзор силуэтом', () => {
    const tree = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'decor',
          { type: 'point', x: 128, y: 128 },
          { scale: 100, baseRadius: 20, sightRadius: 45, sightBottom: 85, label: 'Дерево' },
        ),
      ],
      64,
    );
    const trunk = cellOf(tree, 8, 8);
    const crown = cellOf(tree, 9, 8);
    const [trunkX, trunkY] = surfaceCenter(tree, trunk);

    expect(Math.hypot(trunkX - 128, trunkY - 128)).toBeLessThan(20);
    expect(tree.speeds[trunk]).toBe(0);
    expect(tree.sightBottom[trunk]).toBe(0);

    // Крона шире ствола, но ход не перекрывает и начинается выше глаз.
    expect(tree.speeds[crown]).toBe(100);
    expect(tree.sightBottom[crown]).toBeCloseTo(0.85);
    expect(tree.sightBottom[cellOf(tree, 10, 8)]).toBe(Number.POSITIVE_INFINITY);

    const boulder = service.build(
      board(),
      [grass(), element(2, 'decor', { type: 'point', x: 128, y: 128 }, { scale: 420, label: 'Валун' })],
      64,
    );

    expect(boulder.speeds[cellOf(boulder, 10, 8)]).toBe(0);
    expect(boulder.sightBottom[cellOf(boulder, 10, 8)]).toBe(0);
  });

  it('по умолчанию берёт перекрытие обзора с основания, а не с круга на якоре', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'decor',
          { type: 'point', x: 128, y: 128 },
          {
            scale: 200,
            footprint: {
              shape: 'polygon',
              points: [
                [-30, -40],
                [30, -40],
                [30, -5],
                [-30, -5],
              ],
            },
          },
          10,
        ),
      ],
      64,
    );

    // Видимая масса обведена над якорем: там и перекрытие.
    expect(grid.sightBottom[cellOf(grid, 8, 6)]).toBe(0);
    // Под якорем, где стоял бы круг, клетка прозрачна — тень не начинается
    // на земле перед камнем.
    expect(grid.sightBottom[cellOf(grid, 8, 9)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('держит полигон основания и низ силуэта из новых полей', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'decor',
          { type: 'point', x: 128, y: 128 },
          {
            footprint: {
              shape: 'polygon',
              points: [
                [-20, -20],
                [20, -20],
                [20, 20],
                [-20, 20],
              ],
            },
            silhouette: { shape: 'circle', radius: 30, bottom: 16 },
            label: 'Валун',
          },
        ),
      ],
      64,
    );

    // Внутри основания ход закрыт, обзор перекрыт от земли.
    expect(grid.speeds[cellOf(grid, 8, 8)]).toBe(0);
    expect(grid.sightBottom[cellOf(grid, 8, 8)]).toBe(0);

    // Между основанием и краем силуэта ход открыт, обзор — с высоты 16.
    expect(grid.speeds[cellOf(grid, 9, 8)]).toBe(100);
    expect(grid.sightBottom[cellOf(grid, 9, 8)]).toBeCloseTo(16 / 64);

    // За силуэтом обзор свободен.
    expect(grid.sightBottom[cellOf(grid, 10, 8)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('выключает перекрытие обзора флагом', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'decor',
          { type: 'point', x: 128, y: 128 },
          {
            footprint: { shape: 'circle', radius: 20 },
            silhouette: { shape: 'circle', radius: 30, bottom: 0 },
            blocksVision: false,
            label: 'Ковёр',
          },
        ),
      ],
      64,
    );

    expect(grid.speeds[cellOf(grid, 8, 8)]).toBe(0);
    expect(grid.sightBottom[cellOf(grid, 8, 8)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('пропускает проходимый декор, но силуэт всё равно перекрывает обзор', () => {
    const grid = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'decor',
          { type: 'point', x: 128, y: 128 },
          {
            footprint: { shape: 'circle', radius: 20 },
            silhouette: { shape: 'circle', radius: 20, bottom: 0 },
            speed: 100,
            label: 'Куст',
          },
        ),
      ],
      64,
    );

    expect(grid.speeds[cellOf(grid, 8, 8)]).toBe(100);
    expect(grid.sightBottom[cellOf(grid, 8, 8)]).toBe(0);
  });

  it('читает явную скорость элемента вместо пресета', () => {
    const grid = service.build(
      board(),
      [grass(), element(2, 'terrain', { type: 'rect', x: 0, y: 0, w: 64, h: 256 }, { kind: 'dirt', speed: 60 })],
      64,
    );

    expect(grid.speeds[cellOf(grid, 0, 0)]).toBe(60);
    expect(grid.costs[cellOf(grid, 0, 0)]).toBeCloseTo(100 / 60);
    expect(grid.speeds[cellOf(grid, 6, 0)]).toBe(100);
  });

  it('оставляет непокрытую клетку непроходимой', () => {
    const grid = service.build(board(), [], 64);

    expect(grid.kinds[0]).toBe('none');
    expect(grid.speeds[0]).toBe(0);
    expect(grid.costs[0]).toBe(Number.POSITIVE_INFINITY);
  });

  it('закрытый проём держит стену, открытый прорезает', () => {
    const wallShape: SceneShape = {
      type: 'wall',
      points: [
        [64, 72],
        [96, 72],
      ],
      width: 32,
    };
    const closed = service.build(
      board(),
      [
        grass(),
        element(2, 'obstacle', wallShape, { blocksMovement: true, blocksVision: true }),
        element(3, 'opening', wallShape, { state: 'closed' }),
      ],
      64,
    );
    const open = service.build(
      board(),
      [
        grass(),
        element(2, 'obstacle', wallShape, { blocksMovement: true, blocksVision: true }),
        element(3, 'opening', wallShape, { state: 'open' }),
      ],
      64,
    );

    expect(closed.speeds[cellOf(closed, 4, 4)]).toBe(0);
    expect(closed.sightBottom[cellOf(closed, 4, 4)]).toBe(0);
    expect(open.speeds[cellOf(open, 4, 4)]).toBe(100);
    expect(open.sightBottom[cellOf(open, 4, 4)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('окно не пускает, но оставляет обзор выше подоконника', () => {
    const wallShape: SceneShape = {
      type: 'wall',
      points: [
        [64, 100],
        [224, 100],
      ],
      width: 16,
    };
    const windowShape: SceneShape = {
      type: 'wall',
      points: [
        [128, 100],
        [160, 100],
      ],
      width: 16,
    };
    const grid = service.build(
      board(),
      [
        grass(),
        element(2, 'obstacle', wallShape, { blocksMovement: true, blocksVision: true }),
        element(3, 'opening', windowShape, { kind: 'window', state: 'closed', sill: 32 }),
      ],
      64,
    );
    const inWindow = cellOf(grid, 8, 6);
    const inWall = cellOf(grid, 6, 6);

    // Окно занимает клетку, но не пускает: подоконник перекрывает низ,
    // выше него обзор открыт.
    expect(grid.speeds[inWindow]).toBe(0);
    expect(grid.sightBottom[inWindow]).toBe(0);
    expect(grid.sightTop[inWindow]).toBeCloseTo(0.5);
    // Стена рядом с окном непрозрачна на всю высоту.
    expect(grid.sightTop[inWall]).toBe(Number.POSITIVE_INFINITY);
  });

  it('стеклянная дверь закрыта, но прозрачна', () => {
    const doorShape: SceneShape = {
      type: 'wall',
      points: [
        [128, 100],
        [160, 100],
      ],
      width: 16,
    };
    const grid = service.build(
      board(),
      [
        grass(),
        element(
          2,
          'obstacle',
          {
            type: 'wall',
            points: [
              [64, 100],
              [224, 100],
            ],
            width: 16,
          },
          { blocksMovement: true, blocksVision: true },
        ),
        element(3, 'opening', doorShape, { kind: 'door', state: 'closed', blocksVision: false }),
      ],
      64,
    );

    expect(grid.speeds[cellOf(grid, 8, 6)]).toBe(0);
    expect(grid.sightBottom[cellOf(grid, 8, 6)]).toBe(Number.POSITIVE_INFINITY);
  });

  it('переводит мировые координаты в клетку', () => {
    const grid = service.build(board(), [grass()], 64);

    expect(surfaceIndex(grid, 8, 8)).toBe(cellOf(grid, 0, 0));
    expect(surfaceIndex(grid, 255, 255)).toBe(cellOf(grid, 15, 15));
    expect(surfaceIndex(grid, -1, 8)).toBe(-1);
    expect(surfaceIndex(grid, 256, 8)).toBe(-1);
  });
});
