import { describe, expect, it } from 'vitest';
import type { Graphics } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneWallBand } from '@/modules/Roleplay/Scene/Service/SceneWallService';
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
      action?: string;
      data?: { path?: { instructions?: unknown[] } };
    };

    return last.action === 'stroke' ? (last.data?.path?.instructions?.length ?? 0) : 0;
  };

  /** Действия верхнего уровня: заливка и обводки. */
  const actions = (graphics: Graphics): string[] =>
    graphics.context.instructions.map((instruction) => (instruction as { action: string }).action);

  const band = (material?: string): SceneWallBand => ({
    elementId: 7,
    material,
    thickness: 16,
    center: [
      [100, 100],
      [400, 100],
    ],
    polygon: [
      [100, 92],
      [400, 92],
      [400, 108],
      [100, 108],
    ],
  });

  it('площадка перехода — только метка, без рамки', () => {
    const landing = painter.paintElement(opening(2), 1);

    // Только ромб с заливкой: постоянной рамки и внутреннего контура нет.
    expect(actions(landing)).toEqual(['fill']);
  });

  it('лестничный проём — колодец со ступенями и кромкой', () => {
    const hole = painter.paintElement(opening(null), 1);

    expect(actions(hole)).toContain('stroke');
    expect(hole.context.instructions.length).toBeGreaterThan(
      painter.paintElement(opening(2), 1).context.instructions.length,
    );
  });

  /** Пары координат всех заливок заданного цвета. */
  const fillPairs = (graphics: Graphics, color: number): number[][] => {
    const pairs: number[][] = [];
    for (const instruction of graphics.context.instructions as unknown as {
      action?: string;
      data?: { style?: { color?: number }; path?: { instructions?: { action?: string; data?: unknown }[] } };
    }[]) {
      if (instruction.action !== 'fill' || instruction.data?.style?.color !== color) {
        continue;
      }
      for (const path of instruction.data?.path?.instructions ?? []) {
        if (path.action !== 'poly' || !Array.isArray(path.data)) {
          continue;
        }
        // `poly` получает массив точек первым аргументом: [x0, y0, x1, y1, ...].
        const points = path.data[0];
        if (!Array.isArray(points)) {
          continue;
        }
        for (let index = 0; index + 1 < points.length; index += 2) {
          pairs.push([points[index] as number, points[index + 1] as number]);
        }
      }
    }

    return pairs;
  };

  /** Окно в стене толщиной 16 по линии y = 100. */
  const windowOf = (material: string | undefined): SceneElement => ({
    id: 2,
    boardId: 1,
    kind: 'opening',
    shape: {
      type: 'wall',
      width: 16,
      points: [
        [100, 100],
        [196, 100],
      ],
    },
    props: { kind: 'window', state: 'closed', material },
    sortOrder: 0,
    targetBoardId: null,
    asset: null,
  });

  it('подоконник закрывает всю толщину стены цветом материала', () => {
    const pairs = fillPairs(painter.paintElement(windowOf('wood'), 1), 0x7a5233);

    expect(pairs.length).toBeGreaterThan(0);
    const ys = pairs.map((pair) => pair[1]);
    // Стена толщиной 16 с осевой по y = 100: подоконник — от 92 до 108, а не
    // узкая полоса у осевой. Иначе под окном остаётся дыра с травой.
    expect(Math.min(...ys)).toBe(92);
    expect(Math.max(...ys)).toBe(108);
    expect(fillPairs(painter.paintElement(windowOf('plaster'), 1), 0xb8ac96).length).toBeGreaterThan(0);
  });

  it('верхняя грань стены — одна плоская заливка без полос', () => {
    expect(actions(painter.paintWallTop([band('plaster')], 64, 1))).toEqual(['fill']);
  });

  it('швы идут по шагу материала: у кирпича чаще, у штукатурки их нет', () => {
    const plaster = painter.paintWallTop([band('plaster')], 64, 1);
    const wood = painter.paintWallTop([band('wood')], 64, 1);
    const brick = painter.paintWallTop([band('brick')], 64, 1);

    expect(seamSegments(plaster)).toBe(0);
    expect(seamSegments(wood)).toBeGreaterThan(0);
    expect(seamSegments(brick)).toBeGreaterThan(seamSegments(wood));
  });

  it('неизвестный материал стены рисуется как штукатурка', () => {
    expect(actions(painter.paintWallTop([band('gold')], 64, 1))).toEqual(
      actions(painter.paintWallTop([band('plaster')], 64, 1)),
    );
  });

  it('обводка идёт по кольцам силуэта: внешний контур и дырка комнаты', () => {
    const outline = painter.paintWallOutline(
      [
        {
          material: 'plaster',
          thickness: 16,
          polygons: [
            [
              [
                [0, 0],
                [100, 0],
                [100, 100],
                [0, 100],
              ],
              [
                [20, 20],
                [80, 20],
                [80, 80],
                [20, 80],
              ],
            ],
          ],
        },
      ],
      1,
    );

    expect(actions(outline)).toEqual(['stroke', 'stroke']);
  });

  it('тень стены — заливка каждого контура со своей прозрачностью', () => {
    const shadow = painter.paintWallShadow(
      [
        [
          [
            [0, 0],
            [10, 0],
            [10, 10],
            [0, 10],
          ],
        ],
      ],
      0.14,
    );

    expect(actions(shadow)).toEqual(['fill']);
    expect((shadow.context.instructions[0] as unknown as { data: { style: { alpha: number } } }).data.style.alpha).toBe(
      0.14,
    );
  });
});
