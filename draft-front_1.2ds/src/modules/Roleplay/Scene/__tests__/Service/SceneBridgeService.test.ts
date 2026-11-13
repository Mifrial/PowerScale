import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import { SceneBridgeService } from '@/modules/Roleplay/Scene/Service/SceneBridgeService';

const bridge = (shape: SceneElement['shape']): SceneElement => ({
  id: 1,
  boardId: 1,
  kind: 'support',
  shape,
  props: { kind: 'bridge', label: 'Мост' },
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

  it('не рисует мост не по прямоугольнику', () => {
    expect(service.paint(bridge({ type: 'point', x: 0, y: 0 }), 64, 1)).toBeNull();
  });
});
