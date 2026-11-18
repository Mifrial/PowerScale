import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import { SCENE_STAIRS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_STAIRS';
import { SCENE_BRIDGE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BRIDGE';
import { SceneBridgeService } from '@/modules/Roleplay/Scene/Service/SceneBridgeService';

const bridge = (shape: SceneElement['shape'], props: SceneElement['props'] = {}): SceneElement => ({
  id: 1,
  boardId: 1,
  kind: 'support',
  shape,
  props: { kind: 'bridge', label: 'Мост', ...props },
  sortOrder: 0,
  targetBoardId: null,
  asset: null,
});

describe('SceneBridgeService', () => {
  const service = new SceneBridgeService();

  it('рисует горизонтальный и вертикальный настил', () => {
    const horizontal = service.paint(bridge({ type: 'rect', x: 0, y: 0, w: 600, h: 120 }), 64, 1);
    const vertical = service.paint(bridge({ type: 'rect', x: 0, y: 0, w: 120, h: 600 }), 64, 1);

    expect(horizontal?.context.instructions.length).toBeGreaterThan(0);
    expect(vertical?.context.instructions.length).toBeGreaterThan(0);
  });

  it('не добавляет боковую грань и сваи при подъёме настила', () => {
    const flat = service.paint(bridge({ type: 'rect', x: 0, y: 0, w: 600, h: 120 }, { elevation: 0 }), 64, 1);
    const raised = service.paint(
      bridge({ type: 'rect', x: 0, y: 0, w: 600, h: 120 }, { elevation: 48, height: 16 }),
      64,
      1,
    );

    // Высота — данные для Game; в top-down рисунке она меняет только тень.
    expect(raised?.context.instructions.length).toBe(flat?.context.instructions.length);
  });

  it('поворачивает настил вокруг центра', () => {
    const rotated = service.paint(bridge({ type: 'rect', x: 0, y: 0, w: 600, h: 120 }, { rotation: 90 }), 64, 1);

    expect(rotated?.rotation).toBeCloseTo(Math.PI / 2);
    expect(rotated?.pivot.x).toBe(300);
    expect(rotated?.pivot.y).toBe(60);
  });

  it('рисует съезд и его наклонную грань', () => {
    const ramp = service.paint(
      bridge({ type: 'rect', x: 0, y: 0, w: 200, h: 120 }, { kind: 'ramp', elevation: 48, height: 16 }),
      64,
      1,
    );

    expect(ramp?.context.instructions.length).toBeGreaterThan(0);
  });

  /** Заливки верхнего уровня: цвет и прозрачность. */
  const fills = (graphics: ReturnType<SceneBridgeService['paint']>): { color: number; alpha: number }[] =>
    (graphics?.context.instructions ?? [])
      .filter((instruction) => (instruction as { action?: string }).action === 'fill')
      .map((instruction) => {
        const style = (instruction as unknown as { data?: { style?: { color?: number; alpha?: number } } }).data
          ?.style;

        return { color: style?.color ?? -1, alpha: style?.alpha ?? 1 };
      });

  const stairs = (elevation: number): SceneElement =>
    bridge({ type: 'rect', x: 0, y: 0, w: 96, h: 400 }, { kind: 'stairs', elevation });

  it('рисует ступени по подъёму: на два ипари — восемь', () => {
    const low = service.paint(stairs(64), 64, 1);
    const high = service.paint(stairs(128), 64, 1);

    // Каждая ступень — плита с подступенком и носком.
    const risers = fills(high).filter((fill) => fill.color === SCENE_STAIRS.riserColor);
    const noses = fills(high).filter((fill) => fill.color === SCENE_STAIRS.nosingColor);

    expect(risers).toHaveLength(8);
    expect(noses).toHaveLength(8);
    expect(fills(low).filter((fill) => fill.color === SCENE_STAIRS.riserColor)).toHaveLength(4);
  });

  it('проступь светлеет к высокому концу марша', () => {
    const known = new Set<number>([
      SCENE_STAIRS.riserColor,
      SCENE_STAIRS.nosingColor,
      SCENE_STAIRS.edgeColor,
      SCENE_STAIRS.stringerColor,
      SCENE_STAIRS.stringerShadow,
      SCENE_BRIDGE.shadowColor,
    ]);
    const treads = fills(service.paint(stairs(128), 64, 1)).filter(
      (fill) => fill.alpha === 1 && !known.has(fill.color),
    );
    const brightness = treads.map((fill) => ((fill.color >> 16) & 0xff) + ((fill.color >> 8) & 0xff) + (fill.color & 0xff));

    expect(treads).toHaveLength(8);
    // Тон растёт от низкого конца к высокому: подъём читается без высоты.
    expect(brightness).toEqual([...brightness].sort((left, right) => left - right));
    expect(new Set(brightness).size).toBeGreaterThan(1);
  });

  it('без подъёма марш — ровная плита без подступенков', () => {
    const flat = service.paint(stairs(0), 64, 1);

    expect(fills(flat).filter((fill) => fill.color === SCENE_STAIRS.riserColor)).toHaveLength(0);
  });

  it('не рисует мост не по прямоугольнику', () => {
    expect(service.paint(bridge({ type: 'point', x: 0, y: 0 }), 64, 1)).toBeNull();
  });

  it('заливает низкую часть съезда', () => {
    const shape = { type: 'rect' as const, x: 0, y: 0, w: 200, h: 120 };
    const props = { kind: 'ramp' as const, elevation: 48, height: 16 };
    const plain = service.paint(bridge(shape, props), 64, 1, 0);
    const contact = service.paint(bridge(shape, props), 64, 1, 0.5);

    expect(contact?.context.instructions.length ?? 0).toBeGreaterThan(plain?.context.instructions.length ?? 0);
  });
});
