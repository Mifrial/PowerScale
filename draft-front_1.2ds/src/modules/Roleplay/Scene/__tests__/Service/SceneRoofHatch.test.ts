import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { sceneRoofHatches } from '@/modules/Roleplay/Scene/Service/SceneRoofHatch';

const element = (
  id: number,
  kind: SceneElementKind,
  shape: SceneShape,
  props: SceneElementProps = {},
  sortOrder = id,
): SceneElement => ({
  id,
  boardId: 1,
  kind,
  shape,
  props,
  sortOrder,
  targetBoardId: null,
  asset: null,
});

const grass = (): SceneElement =>
  element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 500, h: 400 }, { kind: 'grass', speed: 100 }, 0);

const roof = (): SceneElement =>
  element(
    2,
    'support',
    { type: 'rect', x: 100, y: 100, w: 200, h: 200 },
    {
      kind: 'roof',
      elevation: 128,
      height: 16,
      speed: 100,
      walkable: true,
    },
  );

const stairs = (rotation = 0): SceneElement =>
  element(
    3,
    'support',
    { type: 'rect', x: 120, y: 150, w: 160, h: 40 },
    {
      kind: 'stairs',
      elevation: 128,
      height: 16,
      speed: 100,
      rotation,
    },
  );

describe('sceneRoofHatches', () => {
  it('режет крышу только у высокого торца марша', () => {
    const [hatch] = sceneRoofHatches([grass(), roof(), stairs()], 64);

    expect(hatch?.roofId).toBe(2);
    expect(hatch?.stairsId).toBe(3);
    // Высокий торец — справа: люк в один ипари, а не во весь марш.
    expect(hatch?.polygon).toEqual([
      [280, 150],
      [280, 190],
      [216, 190],
      [216, 150],
    ]);
  });

  it('ставит люк у высокого торца повёрнутого марша', () => {
    const [hatch] = sceneRoofHatches([grass(), roof(), stairs(180)], 64);

    // Поворот на 180°: высокий торец слева.
    expect(hatch?.polygon).toEqual([
      [120, 190],
      [120, 150],
      [184, 150],
      [184, 190],
    ]);
  });

  it('не трогает крышу, если марш на неё не выходит', () => {
    const low = element(
      3,
      'support',
      { type: 'rect', x: 120, y: 150, w: 160, h: 40 },
      {
        kind: 'stairs',
        elevation: 64,
        height: 16,
        speed: 100,
      },
    );

    expect(sceneRoofHatches([grass(), roof(), low], 64)).toEqual([]);
  });
});
