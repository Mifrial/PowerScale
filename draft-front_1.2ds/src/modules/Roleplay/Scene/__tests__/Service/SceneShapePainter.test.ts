import { describe, expect, it } from 'vitest';
import type { Graphics } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneWallChain } from '@/modules/Roleplay/Scene/Service/SceneWallService';
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

  /** Число отрезков в последней обводке: у стены это швы материала. */
  const seamSegments = (graphics: Graphics): number => {
    const instructions = graphics.context.instructions;
    const last = instructions[instructions.length - 1] as unknown as {
      data?: { path?: { instructions?: unknown[] } };
    };

    return last.data?.path?.instructions?.length ?? 0;
  };

  /** Число обводок: торцы у свободных концов добавляют по одной. */
  const strokes = (graphics: Graphics): number =>
    graphics.context.instructions.filter((instruction) => (instruction as { action?: string }).action === 'stroke')
      .length;

  const wallChain = (material?: string): SceneWallChain => ({
    elementId: 7,
    material,
    thickness: 16,
    points: [
      [100, 100],
      [400, 100],
    ],
    closed: false,
    startFree: true,
    endFree: true,
  });

  const cornerChain = (material?: string): SceneWallChain => ({
    elementId: 7,
    material,
    thickness: 16,
    points: [
      [100, 100],
      [400, 100],
      [400, 400],
    ],
    closed: false,
    startFree: true,
    endFree: true,
  });

  it('рисует площадку перехода и лестничный проём по-разному', () => {
    const landing = painter.paintElement(opening(2), 1);
    const hole = painter.paintElement(opening(null), 1);

    expect(landing.context.instructions.length).toBeGreaterThan(0);
    expect(hole.context.instructions.length).toBeGreaterThan(0);
    // Проём — тёмный колодец со ступенями: линий больше, чем у площадки.
    expect(hole.context.instructions.length).toBeGreaterThan(landing.context.instructions.length);
  });

  it('стена получает материал: ребро, фаску и швы по шагу материала', () => {
    const plaster = painter.paintWallChain(wallChain('plaster'), 64, 1);
    const wood = painter.paintWallChain(wallChain('wood'), 64, 1);
    const brick = painter.paintWallChain(wallChain('brick'), 64, 1);

    // Тень, заливка, ребро и фаска есть у любого материала.
    expect(plaster.context.instructions.length).toBeGreaterThan(4);
    // Швы: у досок реже, у кирпича чаще.
    expect(seamSegments(wood)).toBeGreaterThan(seamSegments(plaster));
    expect(seamSegments(brick)).toBeGreaterThan(seamSegments(wood));
  });

  it('неизвестный материал стены рисуется как штукатурка', () => {
    const unknown = painter.paintWallChain(wallChain('gold'), 64, 1);
    const plaster = painter.paintWallChain(wallChain('plaster'), 64, 1);

    expect(unknown.context.instructions.length).toBe(plaster.context.instructions.length);
  });

  it('у цепи без свободных концов нет торцов', () => {
    const open = painter.paintWallChain(cornerChain('plaster'), 64, 1);
    const closed = painter.paintWallChain(
      { ...cornerChain('plaster'), closed: true, startFree: false, endFree: false },
      64,
      1,
    );

    expect(strokes(closed)).toBeLessThan(strokes(open));
  });

  it('стык стены рисуется в материале самой широкой ленты', () => {
    const joint = painter.paintWallJoint([100, 0], 16, 1, 'wood');

    expect(joint.context.instructions.length).toBeGreaterThan(0);
  });
});
