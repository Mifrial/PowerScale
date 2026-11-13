import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SCENE_BRIDGE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BRIDGE';
import { SCENE_NAVIGATION } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_NAVIGATION';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import {
  isSceneDeck,
  sceneDeckBaseElevation,
  sceneDeckClearance,
  sceneDeckThickness,
  sceneRampSlope,
  sceneSlopeElevation,
  sceneSurfaceElevation,
} from '@/modules/Roleplay/Scene/Service/SceneHeight';

const RESOLUTION = 64;

const element = (
  id: number,
  kind: SceneElementKind,
  shape: SceneShape,
  props: SceneElementProps = {},
): SceneElement => ({
  id,
  boardId: 1,
  kind,
  shape,
  props,
  sortOrder: id,
  targetBoardId: null,
  asset: null,
});

const deckShape: SceneShape = { type: 'rect', x: 100, y: 100, w: 200, h: 60 };

describe('SceneHeight', () => {
  it('берёт высоту поверхности из props, у воды — из константы', () => {
    expect(sceneSurfaceElevation(element(1, 'terrain', deckShape, { kind: 'grass' }), RESOLUTION)).toBe(0);
    expect(sceneSurfaceElevation(element(2, 'terrain', deckShape, { kind: 'water' }), RESOLUTION)).toBe(
      RESOLUTION * SCENE_SURFACE.waterSurfaceIpari,
    );
    expect(
      sceneSurfaceElevation(element(3, 'support', deckShape, { kind: 'bridge', elevation: 120 }), RESOLUTION),
    ).toBe(120);
  });

  it('без явной толщины берёт пресет настила', () => {
    expect(sceneDeckThickness(element(1, 'support', deckShape, { kind: 'bridge' }), RESOLUTION)).toBeCloseTo(
      RESOLUTION * SCENE_BRIDGE.thicknessIpari,
    );
    expect(sceneDeckThickness(element(2, 'support', deckShape, { kind: 'bridge', height: 16 }), RESOLUTION)).toBe(16);
  });

  it('мост и съезд — настил, остальные опоры — нет', () => {
    expect(isSceneDeck(element(1, 'support', deckShape, { kind: 'bridge' }))).toBe(true);
    expect(isSceneDeck(element(2, 'support', deckShape, { kind: 'ramp' }))).toBe(true);
    expect(isSceneDeck(element(3, 'support', deckShape, { kind: 'ground' }))).toBe(false);
    expect(isSceneDeck(element(4, 'terrain', deckShape, { kind: 'grass' }))).toBe(false);
  });

  it('ищет самую высокую поверхность под настилом', () => {
    const grass = element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 2560, h: 2560 }, { kind: 'grass' });
    const deck = element(2, 'support', deckShape, { kind: 'bridge', elevation: 100, height: 16 });
    const lower = element(
      3,
      'support',
      { type: 'rect', x: 120, y: 120, w: 80, h: 40 },
      {
        kind: 'ground',
        elevation: 40,
      },
    );

    expect(sceneDeckBaseElevation(deck, [grass, deck], RESOLUTION)).toBe(0);
    expect(sceneDeckBaseElevation(deck, [grass, lower, deck], RESOLUTION)).toBe(40);
  });

  it('зазор — от низа настила до поверхности под ним', () => {
    const grass = element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 2560, h: 2560 }, { kind: 'grass' });
    const low = element(2, 'support', deckShape, { kind: 'bridge', elevation: 0, height: 16 });
    const high = element(3, 'support', deckShape, { kind: 'bridge', elevation: 100, height: 16 });

    expect(sceneDeckClearance(low, [grass, low], RESOLUTION)).toBe(-16);
    expect(sceneDeckClearance(high, [grass, high], RESOLUTION)).toBe(84);
    expect(sceneDeckClearance(high, [grass, high], RESOLUTION)).toBeGreaterThan(
      RESOLUTION * SCENE_NAVIGATION.bodyHeightIpari,
    );
  });

  it('считает наклон съезда вдоль длинной стороны', () => {
    const grass = element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 2560, h: 2560 }, { kind: 'grass' });
    const ramp = element(
      2,
      'support',
      { type: 'rect', x: 100, y: 100, w: 200, h: 60 },
      {
        kind: 'ramp',
        elevation: 96,
      },
    );
    const slope = sceneRampSlope(ramp, [grass, ramp], RESOLUTION);

    expect(slope).not.toBeNull();
    expect(slope?.from).toEqual([100, 130]);
    expect(slope?.to).toEqual([300, 130]);
    expect(slope?.low).toBe(0);
    expect(slope?.high).toBe(96);
    expect(sceneSlopeElevation(slope!, [100, 130])).toBe(0);
    expect(sceneSlopeElevation(slope!, [200, 130])).toBeCloseTo(48);
    expect(sceneSlopeElevation(slope!, [300, 130])).toBeCloseTo(96);
  });

  it('поворачивает наклон съезда вместе с опорой', () => {
    const grass = element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 2560, h: 2560 }, { kind: 'grass' });
    const ramp = element(
      2,
      'support',
      { type: 'rect', x: 100, y: 100, w: 200, h: 60 },
      {
        kind: 'ramp',
        elevation: 96,
        rotation: 90,
      },
    );
    const slope = sceneRampSlope(ramp, [grass, ramp], RESOLUTION);

    expect(slope?.from).toEqual([200, 30]);
    expect(slope?.to).toEqual([200, 230]);
  });

  it('находит поверхность под повёрнутым настилом', () => {
    const grass = element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 2560, h: 2560 }, { kind: 'grass' });
    const deck = element(2, 'support', deckShape, { kind: 'bridge', elevation: 100, rotation: 90 });

    expect(sceneDeckBaseElevation(deck, [grass, deck], RESOLUTION)).toBe(0);
  });
});
