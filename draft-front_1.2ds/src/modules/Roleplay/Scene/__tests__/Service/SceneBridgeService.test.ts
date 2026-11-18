import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import { SCENE_STAIRS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_STAIRS';
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

  const stairs = (elevation: number, railing = false): SceneElement =>
    bridge({ type: 'rect', x: 0, y: 0, w: 96, h: 400 }, { kind: 'stairs', elevation, railing });

  /** Носок ступени: единственная полупрозрачная заливка цвета носка. */
  const noses = (graphics: ReturnType<SceneBridgeService['paint']>): number =>
    fills(graphics).filter(
      (fill) => fill.color === SCENE_STAIRS.nosingColor && fill.alpha === SCENE_STAIRS.nosingAlpha,
    ).length;

  it('рисует ступени по подъёму: на два ипари — восемь', () => {
    expect(noses(service.paint(stairs(128), 64, 1))).toBe(8);
    expect(noses(service.paint(stairs(64), 64, 1))).toBe(4);
  });

  it('проступь светлеет к высокому концу марша', () => {
    const list = fills(service.paint(stairs(128), 64, 1));
    const low = list.findIndex((fill) => fill.color === SCENE_STAIRS.treadLow && fill.alpha === 1);
    const high = list.findIndex((fill) => fill.color === SCENE_STAIRS.treadHigh && fill.alpha === 1);
    const brightness = (color: number): number =>
      ((color >> 16) & 0xff) + ((color >> 8) & 0xff) + (color & 0xff);

    expect(low).toBeGreaterThanOrEqual(0);
    expect(high).toBeGreaterThan(low);
    // Последняя ступень светлее первой: подъём читается без высоты.
    expect(brightness(SCENE_STAIRS.treadHigh)).toBeGreaterThan(brightness(SCENE_STAIRS.treadLow));
  });

  it('тень верхней ступени ложится на нижнюю', () => {
    const list = fills(service.paint(stairs(128), 64, 1));
    const base = list.findIndex((fill) => fill.color === SCENE_STAIRS.treadLow && fill.alpha === 1);
    const shadow = list.slice(base + 1, base + 1 + SCENE_STAIRS.shadeSlices);
    const brightness = (color: number): number =>
      ((color >> 16) & 0xff) + ((color >> 8) & 0xff) + (color & 0xff);

    expect(shadow).toHaveLength(SCENE_STAIRS.shadeSlices);
    // Полосы идут от низкой кромки к высокой: у высокой тень плотнее.
    expect(brightness(shadow[0].color)).toBeGreaterThan(brightness(shadow[shadow.length - 1].color));
  });

  it('без подъёма марш — ровная плита без ступеней', () => {
    const flat = fills(service.paint(stairs(0), 64, 1));

    // Только подложка: ни ступеней, ни носков.
    expect(flat.filter((fill) => fill.alpha === 1).map((fill) => fill.color)).toEqual([SCENE_STAIRS.baseColor]);
  });

  it('добавляет перила только по свойству', () => {
    const plain = fills(service.paint(stairs(128), 64, 1));
    const railed = fills(service.paint(stairs(128, true), 64, 1));
    // Заглушки перил: тот же тёмный тон, но своя прозрачность.
    const caps = (list: { color: number; alpha: number }[]): number =>
      list.filter((fill) => fill.color === SCENE_STAIRS.railDark && fill.alpha === 0.9).length;

    expect(caps(plain)).toBe(0);
    expect(caps(railed)).toBeGreaterThan(0);
    expect(railed.length).toBeGreaterThan(plain.length);
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
