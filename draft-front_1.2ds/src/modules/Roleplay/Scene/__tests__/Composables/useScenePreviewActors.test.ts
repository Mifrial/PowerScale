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
