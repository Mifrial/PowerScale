import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SCENE_BRIDGE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BRIDGE';
import { sceneRampSlope } from '@/modules/Roleplay/Scene/Service/SceneHeight';
import { sceneCompass, sceneRampAttachment } from '@/modules/Roleplay/Scene/Service/SceneRampAttachment';

const RESOLUTION = 64;

const element = (id: number, shape: SceneShape, props: SceneElementProps = {}): SceneElement => ({
  id,
  boardId: 1,
  kind: 'support',
  shape,
  props: { kind: 'bridge', height: 16, elevation: 100, speed: 150, ...props },
  sortOrder: id,
  targetBoardId: null,
  asset: null,
});

const grass = (): SceneElement => ({
  id: 1,
  boardId: 1,
  kind: 'terrain',
  shape: { type: 'rect', x: 0, y: 0, w: 2560, h: 2560 },
  props: { kind: 'grass', speed: 100 },
  sortOrder: 0,
  targetBoardId: null,
  asset: null,
});

/** Высокий конец съезда по его наклону, мировые координаты. */
const rampTop = (ramp: SceneElement): [number, number] => {
  const slope = sceneRampSlope(ramp, [grass(), ramp], RESOLUTION);
  if (slope === null) {
    throw new Error('ожидался наклон съезда');
  }

  return slope.to;
};

describe('SceneRampAttachment', () => {
  it('приставляет съезд к дальнему торцу горизонтального моста', () => {
    const deck = element(2, { type: 'rect', x: 100, y: 100, w: 400, h: 120 });
    const attachment = sceneRampAttachment(deck, [grass(), deck], RESOLUTION, 'end');
    if (attachment === null) {
      throw new Error('ожидался съезд к торцу');
    }

    // Подъём 100 единиц при уклоне 1:3 — длина 300, ширина по короткой стороне.
    expect(attachment.length).toBe(300);
    expect(attachment.width).toBe(120);
    expect(attachment.shape).toEqual({ type: 'rect', x: 500, y: 100, w: 300, h: 120 });
    expect(attachment.props).toMatchObject({
      kind: 'ramp',
      elevation: 100,
      height: 16,
      speed: 150,
      rotation: 180,
    });
    expect(attachment.direction).toBe('east');

    const top = rampTop({ ...deck, ...attachment, props: attachment.props });
    expect(top[0]).toBeCloseTo(500, 5);
    expect(top[1]).toBeCloseTo(160, 5);
  });

  it('приставляет съезд к ближнему торцу и смотрит наружу', () => {
    const deck = element(2, { type: 'rect', x: 100, y: 100, w: 400, h: 120 });
    const attachment = sceneRampAttachment(deck, [grass(), deck], RESOLUTION, 'start');
    if (attachment === null) {
      throw new Error('ожидался съезд к торцу');
    }

    expect(attachment.shape).toEqual({ type: 'rect', x: -200, y: 100, w: 300, h: 120 });
    expect(attachment.props.rotation).toBe(0);
    expect(attachment.direction).toBe('west');

    const top = rampTop({ ...deck, ...attachment, props: attachment.props });
    expect(top[0]).toBeCloseTo(100, 5);
    expect(top[1]).toBeCloseTo(160, 5);
  });

  it('поворачивает съезд вместе с мостом', () => {
    const deck = element(2, { type: 'rect', x: 100, y: 100, w: 400, h: 120 }, { rotation: 90 });
    const attachment = sceneRampAttachment(deck, [grass(), deck], RESOLUTION, 'end');
    if (attachment === null) {
      throw new Error('ожидался съезд к торцу');
    }

    // Длинная ось повёрнута на юг: съезд уходит дальше на юг.
    expect(attachment.props.rotation).toBe(270);
    expect(attachment.direction).toBe('south');
    expect(attachment.shape.w).toBe(300);
    expect(attachment.shape.h).toBe(120);

    const top = rampTop({ ...deck, ...attachment, props: attachment.props });
    expect(top[0]).toBeCloseTo(300, 5);
    expect(top[1]).toBeCloseTo(360, 5);
  });

  it('держит уклон и минимальную длину для низкого настила', () => {
    const low = element(2, { type: 'rect', x: 0, y: 0, w: 400, h: 120 }, { elevation: 20 });
    const attachment = sceneRampAttachment(low, [grass(), low], RESOLUTION, 'end');
    if (attachment === null) {
      throw new Error('ожидался съезд к торцу');
    }

    // 20 × 3 = 60 короче двух ипари, поэтому берётся минимум.
    expect(attachment.length).toBe(RESOLUTION * SCENE_BRIDGE.rampMinLengthIpari);
    expect(attachment.rise).toBe(20);
  });

  it('не ставит съезд к настилу на поверхности', () => {
    const flat = element(2, { type: 'rect', x: 100, y: 100, w: 400, h: 120 }, { elevation: 0 });

    expect(sceneRampAttachment(flat, [grass(), flat], RESOLUTION, 'end')).toBeNull();
  });

  it('не ставит съезд к съезду или не прямоугольнику', () => {
    const ramp = element(2, { type: 'rect', x: 100, y: 100, w: 400, h: 120 }, { kind: 'ramp' });
    const circle = element(3, { type: 'circle', cx: 100, cy: 100, r: 50 });

    expect(sceneRampAttachment(ramp, [grass(), ramp], RESOLUTION, 'end')).toBeNull();
    expect(sceneRampAttachment(circle, [grass(), circle], RESOLUTION, 'end')).toBeNull();
  });

  it('переводит направление в сторону света', () => {
    expect(sceneCompass([0, -1])).toBe('north');
    expect(sceneCompass([1, -1])).toBe('north-east');
    expect(sceneCompass([1, 0])).toBe('east');
    expect(sceneCompass([0, 1])).toBe('south');
    expect(sceneCompass([-1, 0])).toBe('west');
  });
});
