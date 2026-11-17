import { describe, expect, it } from 'vitest';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneEnclosure';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { useScenePreviewActors } from '@/modules/Roleplay/Scene/Composables/useScenePreviewActors';

const board = (id: number, width: number, height: number): SceneBoard => ({
  id,
  name: `Поле ${id}`,
  sortOrder: id,
  baseFile: null,
  boundWidth: width,
  boundHeight: height,
  occupancyCell: 16,
  zQuantum: 16,
  gridStep: 0,
});

const element = (
  id: number,
  boardId: number,
  kind: SceneElementKind,
  shape: SceneShape,
  props: SceneElementProps = {},
): SceneElement => ({
  id,
  boardId,
  kind,
  shape,
  props,
  sortOrder: id,
  targetBoardId: null,
  asset: null,
});

const root = board(1, 1024, 1024);
const upper = board(2, 512, 512);
const enclosure: SceneEnclosure = {
  id: 2,
  parentBoardId: 1,
  childBoardId: 2,
  footprint: { type: 'rect', x: 256, y: 256, w: 512, h: 512 },
  elevation: 128,
  label: null,
  sortOrder: 0,
};

const elements: SceneElement[] = [
  element(1, 1, 'terrain', { type: 'rect', x: 0, y: 0, w: 1024, h: 1024 }, { kind: 'grass' }),
  element(2, 2, 'terrain', { type: 'rect', x: 0, y: 0, w: 512, h: 512 }, { kind: 'dirt' }),
  element(10, 1, 'marker', { type: 'point', x: 300, y: 500 }, { role: 'player_start', label: 'Манекен' }),
  element(11, 2, 'marker', { type: 'point', x: 200, y: 200 }, { role: 'npc_spawn', label: 'Токен' }),
];

/**
 * Предпросмотр на втором этаже: актор снизу виден через полупрозрачную плиту,
 * а актор сверху — только через настоящий проём.
 */
function preview(slabCutaway: () => boolean) {
  return useScenePreviewActors({
    enabled: () => true,
    board: () => upper,
    elements: () => elements,
    resolution: () => 64,
    boards: () => [root, upper],
    enclosures: () => [enclosure],
    slabCutaway,
  });
}

describe('useScenePreviewActors', () => {
  // Анимация движения в тестовой среде не нужна: маршрут проверяем сразу.
  const animation = globalThis as {
    requestAnimationFrame?: (callback: (time: number) => void) => number;
    cancelAnimationFrame?: (id: number) => void;
  };
  animation.requestAnimationFrame = () => 0;
  animation.cancelAnimationFrame = () => {};

  /** Сцена с маршем и площадкой: клик по ступеням не должен уводить на этаж. */
  function previewStairs() {
    const stairRoot = board(1, 1024, 1024);
    const stairUpper = board(2, 512, 512);
    const stairEnclosure: SceneEnclosure = {
      id: 3,
      parentBoardId: 1,
      childBoardId: 2,
      footprint: { type: 'rect', x: 256, y: 256, w: 512, h: 512 },
      elevation: 128,
      label: null,
      sortOrder: 0,
    };
    const landing = (
      id: number,
      boardId: number,
      shape: SceneShape,
      targetBoardId: number,
    ): SceneElement => ({
      ...element(id, boardId, 'opening', shape, { kind: 'stairs', state: 'open' }),
      targetBoardId,
    });
    const stairElements: SceneElement[] = [
      element(20, 1, 'terrain', { type: 'rect', x: 0, y: 0, w: 1024, h: 1024 }, { kind: 'grass' }),
      element(21, 2, 'terrain', { type: 'rect', x: 0, y: 0, w: 512, h: 512 }, { kind: 'dirt' }),
      element(22, 1, 'marker', { type: 'point', x: 332, y: 320 }, { role: 'player_start', label: 'Манекен' }),
      element(23, 1, 'support', { type: 'rect', x: 300, y: 300, w: 64, h: 400 }, {
        kind: 'stairs',
        elevation: 128,
        height: 16,
        speed: 100,
      }),
      // Площадка на высоком торце и колодец за ней: пара для перехода.
      landing(24, 1, { type: 'rect', x: 300, y: 600, w: 64, h: 100 }, 2),
      element(25, 1, 'opening', { type: 'rect', x: 300, y: 700, w: 64, h: 68 }, {
        kind: 'stairs',
        state: 'closed',
        blocksMovement: true,
      }),
      landing(26, 2, { type: 'rect', x: 44, y: 344, w: 64, h: 100 }, 1),
      element(27, 2, 'opening', { type: 'rect', x: 44, y: 444, w: 64, h: 68 }, {
        kind: 'stairs',
        state: 'closed',
        blocksMovement: true,
      }),
    ];

    return useScenePreviewActors({
      enabled: () => true,
      board: () => stairRoot,
      elements: () => stairElements,
      resolution: () => 64,
      boards: () => [stairRoot, stairUpper],
      enclosures: () => [stairEnclosure],
      slabCutaway: () => true,
    });
  }

  it('клик по маршу ставит на ступени, а площадка ведёт на этаж', () => {
    const actors = previewStairs();
    actors.select(22);
    actors.onMapClick([332, 500], false, [332, 500], 1);

    // Клик по ступеням — не дверь: маршрут остаётся на нижнем поле и
    // заканчивается в точке клика, без меню «наверх или вниз».
    expect(actors.levelMenu.value).toBeNull();
    const march = actors.route.value;
    expect(march).not.toBeNull();
    expect(march!.segments.every((segment) => segment.boardId === 1)).toBe(true);
    expect(march!.points[march!.points.length - 1]).toEqual([332, 500]);

    // Площадка — верхняя ступень и дверь: клик по ней ведёт на этаж.
    actors.onMapClick([332, 650], false, [332, 650], 1);
    const portal = actors.route.value;
    expect(portal).not.toBeNull();
    expect(portal!.segments.some((segment) => segment.boardId === 2)).toBe(true);
  });

  it('прячет актора верхнего этажа от наблюдателя снизу', () => {
    const actors = preview(() => true);
    actors.select(10);

    // Манекен на первом этаже смотрит вверх: плита второго этажа его не
    // пропускает, поэтому стоящий наверху токен не виден.
    expect(actors.actors.value.find((actor) => actor.id === 11)?.hidden).toBe(true);
  });

  it('показывает актора нижнего этажа сквозь плиту фокуса', () => {
    const actors = preview(() => true);
    actors.select(11);

    // Фокус на втором этаже, наблюдатель на нём: нижний этаж виден планом,
    // и его актор не прячется за полупрозрачной плитой.
    expect(actors.actors.value.find((actor) => actor.id === 10)?.hidden).toBe(false);
  });

  it('без разреза прячет актора нижнего этажа за плитой', () => {
    const actors = preview(() => false);
    actors.select(11);

    expect(actors.actors.value.find((actor) => actor.id === 10)?.hidden).toBe(true);
  });
});
