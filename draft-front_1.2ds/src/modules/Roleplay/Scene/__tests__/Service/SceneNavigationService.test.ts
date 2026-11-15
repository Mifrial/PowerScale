import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SceneNavigationService } from '@/modules/Roleplay/Scene/Service/SceneNavigationService';

const board: SceneBoard = {
  id: 1,
  name: 'Поле',
  sortOrder: 0,
  baseFile: null,
  boundWidth: 1000,
  boundHeight: 800,
  occupancyCell: 16,
  zQuantum: 16,
  gridStep: 32,
};

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
  element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 1000, h: 800 }, { kind: 'grass', speed: 100 }, 0);

const rect = (
  id: number,
  kind: SceneElementKind,
  x: number,
  y: number,
  w: number,
  h: number,
  props: SceneElementProps,
  sortOrder = id,
): SceneElement => element(id, kind, { type: 'rect', x, y, w, h }, props, sortOrder);

const near = (point: ScenePoint, x: number, y: number, radius: number): boolean =>
  Math.hypot(point[0] - x, point[1] - y) <= radius;

const crossesRect = (points: ScenePoint[], x: number, y: number, width: number, height: number): boolean => {
  for (let index = 1; index < points.length; index += 1) {
    const from = points[index - 1];
    const to = points[index];
    const length = Math.hypot(to[0] - from[0], to[1] - from[1]);
    const steps = Math.max(1, Math.ceil(length / 5));
    for (let sample = 0; sample <= steps; sample += 1) {
      const t = sample / steps;
      const px = from[0] + (to[0] - from[0]) * t;
      const py = from[1] + (to[1] - from[1]) * t;
      if (px >= x && px <= x + width && py >= y && py <= y + height) {
        return true;
      }
    }
  }

  return false;
};

/** Расстояние от точки до ближайшего отрезка полилинии. */
const minDistance = (points: ScenePoint[], x: number, y: number): number => {
  let best = Number.POSITIVE_INFINITY;
  for (let index = 1; index < points.length; index += 1) {
    const from = points[index - 1];
    const to = points[index];
    const dx = to[0] - from[0];
    const dy = to[1] - from[1];
    const length = dx * dx + dy * dy;
    const t = length <= 0 ? 0 : Math.max(0, Math.min(1, ((x - from[0]) * dx + (y - from[1]) * dy) / length));
    best = Math.min(best, Math.hypot(x - (from[0] + dx * t), y - (from[1] + dy * t)));
  }

  return best;
};

describe('SceneNavigationService', () => {
  const service = new SceneNavigationService();
  const options = { agentRadius: 10 };

  it('строит маршрут по открытому лугу', () => {
    const route = service.route(board, [grass()], 64, [100, 100], [900, 700], options);

    expect(route).not.toBeNull();
    expect(route?.points[0]).toEqual([100, 100]);
    expect(route?.points[route.points.length - 1]).toEqual([900, 700]);
    expect(route?.distance).toBeGreaterThanOrEqual(1000);
    expect(route?.cost).toBeGreaterThan(0);
  });

  it('ведёт маршрут через проём в стене', () => {
    const elements = [
      grass(),
      rect(2, 'obstacle', 500, 0, 20, 300, { blocksMovement: true, blocksVision: true }),
      rect(3, 'obstacle', 500, 500, 20, 300, { blocksMovement: true, blocksVision: true }),
    ];
    const route = service.route(board, elements, 64, [200, 400], [800, 400], options);

    expect(route).not.toBeNull();
    expect(route).not.toBeNull();
    expect(crossesRect(route?.points ?? [], 500, 300, 20, 200)).toBe(true);
  });

  it('предпочитает мост реке и считает его дешевле', () => {
    const water = rect(2, 'terrain', 450, 0, 100, 800, { kind: 'water', speed: 20 }, 10);
    const bridge = rect(3, 'support', 450, 300, 100, 200, { kind: 'bridge', speed: 150 }, 20);
    const withoutBridge = service.route(board, [grass(), water], 64, [200, 400], [800, 400], options);
    const withBridge = service.route(board, [grass(), water, bridge], 64, [200, 400], [800, 400], options);

    expect(withoutBridge).not.toBeNull();
    expect(withBridge).not.toBeNull();
    expect(withBridge).not.toBeNull();
    expect(crossesRect(withBridge?.points ?? [], 450, 300, 100, 200)).toBe(true);
    expect(withBridge?.cost).toBeLessThan(withoutBridge?.cost ?? Number.POSITIVE_INFINITY);
  });

  it('не срезает через воду, если мост дешевле', () => {
    const water = rect(2, 'terrain', 450, 0, 100, 800, { kind: 'water', speed: 20 }, 10);
    // Мост поперёк реки: короткие стороны лежат на берегах, перила — вдоль
    // течения. Маршрут входит с торца и не задевает перила.
    const bridge = rect(3, 'support', 450, 410, 100, 80, { kind: 'bridge', speed: 150 }, 20);
    const options = { agentRadius: 10 };
    const withBridge = service.route(board, [grass(), water, bridge], 64, [200, 450], [800, 450], options);
    const withoutBridge = service.route(board, [grass(), water], 64, [200, 450], [800, 450], options);

    expect(withBridge).not.toBeNull();
    expect(withoutBridge).not.toBeNull();
    expect(withBridge?.cost).toBeLessThan(withoutBridge?.cost ?? Number.POSITIVE_INFINITY);
    expect(crossesRect(withBridge?.points ?? [], 450, 410, 100, 80)).toBe(true);

    const inWaterOutsideBridge = (withBridge?.points ?? []).some((point, index, points) => {
      if (index === 0) return false;
      const from = points[index - 1];
      const length = Math.hypot(point[0] - from[0], point[1] - from[1]);
      const steps = Math.max(1, Math.ceil(length / 5));
      for (let sample = 0; sample <= steps; sample += 1) {
        const t = sample / steps;
        const x = from[0] + (point[0] - from[0]) * t;
        const y = from[1] + (point[1] - from[1]) * t;
        if (x >= 450 && x <= 550 && (y < 410 || y > 490)) return true;
      }

      return false;
    });
    expect(inWaterOutsideBridge).toBe(false);
  });

  it('не пропускает агента в щель уже его диаметра', () => {
    const elements = [
      grass(),
      rect(2, 'obstacle', 500, 0, 20, 390, { blocksMovement: true, blocksVision: true }),
      rect(3, 'obstacle', 500, 410, 20, 390, { blocksMovement: true, blocksVision: true }),
    ];
    const route = service.route(board, elements, 64, [200, 400], [800, 400], options);

    expect(route).toBeNull();
  });

  it('пересекает мост по середине, а не через дальний угол', () => {
    const water = rect(2, 'terrain', 450, 0, 100, 800, { kind: 'water', speed: 20 }, 10);
    const bridge = rect(3, 'support', 400, 450, 200, 100, { kind: 'bridge', speed: 150 }, 20);
    const route = service.route(board, [grass(), water, bridge], 64, [300, 500], [700, 500], options);

    expect(route).not.toBeNull();
    // Вход и выход — по коротким сторонам; через перила у дальнего угла не
    // лезем, маршрут идёт серединой настила.
    const crossesMiddle = route?.points.some(
      (point) => point[1] >= 480 && point[1] <= 520 && point[0] >= 400 && point[0] <= 600,
    );
    expect(crossesMiddle).toBe(true);
    expect(route?.points.some((point) => near(point, 600, 450, 16))).toBe(false);
  });

  it('не срезает через длинный разрез триангуляции у выреза', () => {
    const large: SceneBoard = { ...board, boundWidth: 2000, boundHeight: 2000 };
    const largeGrass = element(
      1,
      'terrain',
      { type: 'rect', x: 0, y: 0, w: 2000, h: 2000 },
      { kind: 'grass', speed: 100 },
      0,
    );
    const tree = element(
      2,
      'decor',
      { type: 'point', x: 1000, y: 1000 },
      { scale: 420, baseRadius: 30, label: 'Дерево' },
      10,
    );
    const route = new SceneNavigationService().route(large, [largeGrass, tree], 64, [600, 1000], [1400, 1000], {
      agentRadius: 35.2,
    });

    expect(route).not.toBeNull();
    // Прямая 800; обход ствола добавляет немного, а разрез к углу поля — сотни.
    expect(route?.distance).toBeLessThan(950);
  });

  it('пересобирает сетку при смене радиуса основания', () => {
    const tree = element(
      2,
      'decor',
      { type: 'point', x: 500, y: 400 },
      { scale: 420, baseRadius: 30, label: 'Дерево' },
      10,
    );
    const wide = service.route(board, [grass(), tree], 64, [400, 400], [600, 400], options);
    const narrow = service.route(
      board,
      [grass(), { ...tree, props: { ...tree.props, baseRadius: 2 } }],
      64,
      [400, 400],
      [600, 400],
      options,
    );

    expect(wide).not.toBeNull();
    expect(narrow).not.toBeNull();
    expect(narrow?.distance).toBeLessThan(wide?.distance ?? 0);
  });

  it('обходит полигон основания с запасом тела', () => {
    const boulder = element(
      2,
      'decor',
      { type: 'point', x: 500, y: 400 },
      {
        footprint: {
          shape: 'polygon',
          points: [
            [-60, -60],
            [60, -60],
            [60, 60],
            [-60, 60],
          ],
        },
        speed: 0,
        label: 'Валун',
      },
      10,
    );
    const route = service.route(board, [grass(), boulder], 64, [400, 400], [600, 400], options);

    expect(route).not.toBeNull();
    expect(route?.points.some((point) => near(point, 500, 400, 60))).toBe(false);
    expect(route?.distance).toBeGreaterThan(200);
  });

  it('не вычитает проходимый декор из навмеша', () => {
    const grassTuft = element(
      2,
      'decor',
      { type: 'point', x: 500, y: 400 },
      {
        footprint: {
          shape: 'polygon',
          points: [
            [-60, -60],
            [60, -60],
            [60, 60],
            [-60, 60],
          ],
        },
        speed: 100,
        blocksVision: false,
        label: 'Куст',
      },
      10,
    );
    const route = service.route(board, [grass(), grassTuft], 64, [400, 400], [600, 400], options);

    expect(route).not.toBeNull();
    expect(route?.distance).toBeLessThan(210);
  });

  it('обходит основание декора', () => {
    const tree = element(2, 'decor', { type: 'point', x: 500, y: 400 }, { scale: 420, label: 'Валун' });
    const route = service.route(board, [grass(), tree], 64, [400, 400], [600, 400], options);

    expect(route).not.toBeNull();
    expect(route?.points.some((point) => near(point, 500, 400, 40))).toBe(false);
    expect(route?.distance).toBeGreaterThan(200);
  });

  it('обходит основание коротким крюком, а не через угол поля', () => {
    const tree = element(2, 'decor', { type: 'point', x: 700, y: 400 }, { scale: 420, label: 'Дерево' }, 10);
    const route = service.route(board, [grass(), tree], 64, [300, 430], [900, 370], options);
    const straight = Math.hypot(600, -60);

    expect(route).not.toBeNull();
    // Обход круга радиусом около 90 добавляет немного; крюк к углу поля — втрое.
    expect(route?.distance).toBeLessThan(straight * 1.5);
  });

  it('не считает кромку выреза дорогой через препятствие', () => {
    const tree = element(2, 'decor', { type: 'point', x: 500, y: 400 }, { scale: 420, label: 'Дерево' }, 10);
    const route = service.route(board, [grass(), tree], 64, [400, 400], [600, 400], options);

    expect(route).not.toBeNull();
    // Путь по кромке обрезки идёт по траве, а не по «непроходимой» цене.
    expect(route?.cost).toBeLessThan((route?.distance ?? 0) * 1.5);
  });

  it('обходит ствол с зазором тела, а не кольца', () => {
    const tree = element(
      2,
      'decor',
      { type: 'point', x: 500, y: 400 },
      { scale: 420, baseRadius: 10, label: 'Дерево' },
      10,
    );
    // Радиус по умолчанию — тело 0.3 ипари, а не кольцо.
    const route = service.route(board, [grass(), tree], 64, [300, 400], [700, 400]);

    expect(route).not.toBeNull();
    const trunk = 64 * 4.2 * 0.1;
    const body = 64 * 0.3;
    const clearance = minDistance(route?.points ?? [], 500, 400);
    // Ствол не задет, но и «база» 0.55 ипари не огибается.
    expect(clearance).toBeGreaterThan(trunk);
    expect(clearance).toBeLessThan(trunk + body + 8);
  });

  it('настил ниже шага остаётся полом, а не уровнем', () => {
    const water = rect(2, 'terrain', 450, 0, 100, 800, { kind: 'water', speed: 20 }, 10);
    const bridge = rect(3, 'support', 450, 300, 100, 100, { kind: 'bridge', speed: 150, elevation: 16 }, 20);
    const route = service.route(board, [grass(), water, bridge], 64, [200, 350], [800, 350], options);

    expect(route).not.toBeNull();
    expect(route?.segments.every((segment) => segment.level === 0)).toBe(true);
    expect(crossesRect(route?.points ?? [], 450, 300, 100, 100)).toBe(true);
  });

  it('ведёт по приподнятому настилу с подъёмом и спуском', () => {
    const water = rect(2, 'terrain', 450, 0, 100, 800, { kind: 'water', speed: 20 }, 10);
    // 0.75 ипари: выше шага, но в пределах подъёма; зазор под настилом мал.
    const bridge = rect(
      3,
      'support',
      450,
      300,
      100,
      100,
      { kind: 'bridge', speed: 150, elevation: 48, height: 16 },
      20,
    );
    const route = service.route(board, [grass(), water, bridge], 64, [200, 350], [800, 350], options);
    const waterOnly = service.route(board, [grass(), water], 64, [200, 350], [800, 350], options);

    expect(route).not.toBeNull();
    expect(route?.segments.some((segment) => segment.level === 1)).toBe(true);
    expect(crossesRect(route?.points ?? [], 450, 300, 100, 100)).toBe(true);
    expect(route?.cost).toBeLessThan(waterOnly?.cost ?? Number.POSITIVE_INFINITY);
  });

  it('пропускает под высоким настилом и не пускает на него без съезда', () => {
    const water = rect(2, 'terrain', 450, 0, 100, 800, { kind: 'water', speed: 20 }, 10);
    // 1.56 ипари: зазор 1.31 ипари — под мостом пройти можно, но подъём
    // выше порога, поэтому настил без съезда недоступен.
    const bridge = rect(
      3,
      'support',
      450,
      300,
      100,
      100,
      { kind: 'bridge', speed: 150, elevation: 100, height: 16 },
      20,
    );
    const route = service.route(board, [grass(), water, bridge], 64, [200, 350], [800, 350], options);

    expect(route).not.toBeNull();
    expect(route?.segments.every((segment) => segment.level === 0)).toBe(true);
    expect(crossesRect(route?.points ?? [], 450, 300, 100, 100)).toBe(true);
    expect(route?.segments.some((segment) => segment.covered)).toBe(true);
    // Закрыт только кусок под настилом, остальной маршрут открыт.
    expect(route?.segments.some((segment) => !segment.covered)).toBe(true);
  });

  it('пересобирает сетку при смене высоты настила', () => {
    const water = rect(2, 'terrain', 450, 0, 100, 800, { kind: 'water', speed: 20 }, 10);
    const flat = rect(3, 'support', 450, 300, 100, 100, { kind: 'bridge', speed: 150, elevation: 0 }, 20);
    const raised = rect(3, 'support', 450, 300, 100, 100, { kind: 'bridge', speed: 150, elevation: 48 }, 20);
    const cached = new SceneNavigationService();

    const first = cached.route(board, [grass(), water, flat], 64, [200, 350], [800, 350], options);
    const second = cached.route(board, [grass(), water, raised], 64, [200, 350], [800, 350], options);

    expect(first?.segments.every((segment) => segment.level === 0)).toBe(true);
    expect(second?.segments.some((segment) => segment.level === 1)).toBe(true);
  });

  it('съезд соединяет землю с высоким настилом', () => {
    // Настил выше порога подъёма: без съезда на него не попасть.
    const deck = rect(2, 'support', 600, 300, 200, 100, { kind: 'bridge', speed: 150, elevation: 100, height: 16 }, 20);
    const ramp = rect(3, 'support', 450, 320, 150, 60, { kind: 'ramp', speed: 150, elevation: 100, height: 16 }, 15);
    const withoutRamp = service.route(board, [grass(), deck], 64, [300, 350], [700, 350], options);
    const withRamp = service.route(board, [grass(), deck, ramp], 64, [300, 350], [700, 350], options);

    expect(withoutRamp).toBeNull();
    expect(withRamp).not.toBeNull();
    expect(withRamp?.segments.some((segment) => segment.level > 0)).toBe(true);
    expect(crossesRect(withRamp?.points ?? [], 450, 320, 150, 60)).toBe(true);
    // Съезд — постепенный подъём, а не спуск с кромки.
    expect(withRamp?.segments.some((segment) => segment.transition?.type === 'drop')).toBe(false);
  });

  it('перелезает через перила только с доплатой и обходит их по торцу', () => {
    // Настил во всю доску: торцы за краем поля, поэтому единственный путь
    // с юга на север — через перила. Перила выше настила на 0.5 ипари.
    const bridge = rect(2, 'support', -1000, 350, 3000, 100, { kind: 'bridge', speed: 150 }, 20);
    const across = service.route(board, [grass(), bridge], 64, [500, 500], [500, 300], options);
    const along = service.route(board, [grass(), bridge], 64, [50, 400], [950, 400], options);

    expect(across).not.toBeNull();
    expect(across?.segments.some((segment) => segment.level > 0)).toBe(true);
    expect(across?.segments.some((segment) => segment.transition?.type === 'climb')).toBe(true);
    // Обратно с перил — спуск, а не второй подъём.
    expect(across?.segments.some((segment) => segment.transition?.type === 'drop')).toBe(true);

    // Вдоль настила маршрут остаётся внутри и перил не касается.
    expect(along).not.toBeNull();
    expect(along?.segments.every((segment) => segment.level === 0)).toBe(true);
  });

  it('находит все уровни под точкой', () => {
    const water = rect(2, 'terrain', 450, 0, 100, 800, { kind: 'water', speed: 20 }, 10);
    const bridge = rect(
      3,
      'support',
      450,
      300,
      100,
      100,
      { kind: 'bridge', speed: 150, elevation: 100, height: 16 },
      20,
    );
    const mesh = service.build(board, [grass(), water, bridge], 64, options);
    if (mesh === null) {
      throw new Error('ожидалась сетка навигации');
    }

    const levels = service.levelsAt(mesh, [500, 350]);
    expect(levels.map((level) => level.level)).toEqual([1, 0]);
    expect(service.levelsAt(mesh, [200, 350]).map((level) => level.level)).toEqual([0]);
  });

  it('ведёт по указанному уровню цели', () => {
    const water = rect(2, 'terrain', 450, 0, 100, 800, { kind: 'water', speed: 20 }, 10);
    const bridge = rect(
      3,
      'support',
      450,
      300,
      100,
      100,
      { kind: 'bridge', speed: 150, elevation: 100, height: 16 },
      20,
    );
    const under = service.route(board, [grass(), water, bridge], 64, [200, 350], [800, 350], {
      ...options,
      toLevel: 0,
    });
    const onDeck = service.route(board, [grass(), water, bridge], 64, [200, 350], [800, 350], {
      ...options,
      toLevel: 1,
    });

    expect(under).not.toBeNull();
    expect(under?.segments.every((segment) => segment.level === 0)).toBe(true);
    expect(onDeck).toBeNull();
  });

  it('делает мост над врезанным руслом уровнем, а воду под ним — проходимой', () => {
    const water = rect(2, 'terrain', 450, 0, 100, 800, { kind: 'water', speed: 20, depth: 96 }, 10);
    const bridge = rect(3, 'support', 400, 300, 200, 100, { kind: 'bridge', speed: 150, elevation: 0, height: 16 }, 20);
    const elements = [grass(), water, bridge];
    const mesh = service.build(board, elements, 64, options);
    if (mesh === null) {
      throw new Error('ожидалась сетка навигации');
    }

    // На мосту два уровня: настил и вода под ним.
    expect(service.levelsAt(mesh, [500, 350]).map((level) => level.level)).toEqual([1, 0]);
    expect(service.levelsAt(mesh, [200, 350]).map((level) => level.level)).toEqual([0]);

    // Под настилом вода остаётся проходимой: маршрут идёт по уровню 0.
    const under = service.route(board, elements, 64, [500, 320], [500, 380], {
      ...options,
      fromLevel: 0,
      toLevel: 0,
    });
    expect(under).not.toBeNull();
    expect(under?.segments.every((segment) => segment.level === 0)).toBe(true);

    // На настил можно войти с берега и идти по уровню 1.
    const onDeck = service.route(board, elements, 64, [300, 350], [500, 350], {
      ...options,
      toLevel: 1,
    });
    expect(onDeck).not.toBeNull();
    expect(onDeck?.segments.some((segment) => segment.level === 1)).toBe(true);
  });

  it('спускает с торца настила, но не поднимает обратно', () => {
    // 1.4 ипари: спуск в пределах dropIpari, подъём выше climbIpari.
    const deck = rect(2, 'support', 450, 300, 100, 100, { kind: 'bridge', speed: 150, elevation: 90, height: 16 }, 20);
    const down = service.route(board, [grass(), deck], 64, [500, 350], [300, 350], {
      ...options,
      fromLevel: 1,
    });
    const up = service.route(board, [grass(), deck], 64, [300, 350], [500, 350], {
      ...options,
      toLevel: 1,
    });

    expect(down).not.toBeNull();
    expect(down?.segments[0]?.level).toBe(1);
    expect(down?.segments.some((segment) => segment.transition?.type === 'drop')).toBe(true);
    // Вверх 1.4 ипари не поднять: звена нет, настил недоступен.
    expect(up).toBeNull();
  });

  it('не спускает с настила выше dropIpari', () => {
    const deck = rect(2, 'support', 450, 300, 100, 100, { kind: 'bridge', speed: 150, elevation: 140, height: 16 }, 20);
    const down = service.route(board, [grass(), deck], 64, [500, 350], [300, 350], {
      ...options,
      fromLevel: 1,
    });

    expect(down).toBeNull();
  });

  it('проходит щель шириной 1 ипари телом 0.3 и не проходит 0.4', () => {
    // Шахматные стены: верхняя заканчивается на y=400, нижняя начинается там
    // же; единственный проход — щель между ними по x.
    const walls = (gap: number): SceneElement[] => [
      grass(),
      rect(2, 'obstacle', 400, 0, 64, 400, { blocksMovement: true }),
      rect(3, 'obstacle', 464 + gap, 400, 64, 400, { blocksMovement: true }),
    ];

    const wide = service.route(board, walls(64), 64, [300, 400], [700, 400]);
    const narrow = service.route(board, walls(25.6), 64, [300, 400], [700, 400]);

    expect(wide).not.toBeNull();
    expect(narrow).toBeNull();
  });
});
