import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
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
