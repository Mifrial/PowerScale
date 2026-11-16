import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import { SceneShapePainter } from '@/modules/Roleplay/Scene/Service/SceneShapePainter';

const opening = (targetBoardId: number | null): SceneElement => ({
  id: 1,
  boardId: 1,
  kind: 'opening',
  shape: { type: 'rect', x: 100, y: 100, w: 144, h: 190 },
  props: { kind: 'stairs', state: targetBoardId === null ? 'closed' : 'open' },
  sortOrder: 0,
  targetBoardId,
  asset: null,
});

describe('SceneShapePainter', () => {
  const painter = new SceneShapePainter();

  it('рисует площадку перехода и лестничный проём по-разному', () => {
    const landing = painter.paintElement(opening(2), 1);
    const hole = painter.paintElement(opening(null), 1);

    expect(landing.context.instructions.length).toBeGreaterThan(0);
    expect(hole.context.instructions.length).toBeGreaterThan(0);
    // Проём — тёмный колодец со ступенями: линий больше, чем у площадки.
    expect(hole.context.instructions.length).toBeGreaterThan(landing.context.instructions.length);
  });
});
