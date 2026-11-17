import { describe, expect, it } from 'vitest';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SCENE_BRIDGE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BRIDGE';
import { SCENE_NAVIGATION } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_NAVIGATION';
import {
  isSceneDeck,
  isSceneRamp,
  isWalkableRoof,
  sceneDeckBaseElevation,
  sceneDeckClearance,
  sceneDeckLowestBaseElevation,
  sceneDeckThickness,
  sceneRampSlope,
  sceneSlopeElevation,
  sceneSurfaceElevation,
  sceneSurfaceUnder,
  sceneWaterDepth,
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
  it('берёт высоту поверхности из props, у воды — из глубины', () => {
    expect(sceneSurfaceElevation(element(1, 'terrain', deckShape, { kind: 'grass' }))).toBe(0);
    expect(sceneSurfaceElevation(element(2, 'terrain', deckShape, { kind: 'water' }))).toBe(0);
    expect(sceneSurfaceElevation(element(4, 'terrain', deckShape, { kind: 'water', depth: 32 }))).toBe(-32);
    expect(sceneSurfaceElevation(element(3, 'support', deckShape, { kind: 'bridge', elevation: 120 }))).toBe(120);
  });

  it('глубина воды: нет поля — ноль, отрицательная — ноль', () => {
    expect(sceneWaterDepth(element(1, 'terrain', deckShape, { kind: 'water' }))).toBe(0);
    expect(sceneWaterDepth(element(2, 'terrain', deckShape, { kind: 'water', depth: 24 }))).toBe(24);
    expect(sceneWaterDepth(element(3, 'terrain', deckShape, { kind: 'water', depth: -24 }))).toBe(0);
  });

  it('без явной толщины берёт пресет настила', () => {
    expect(sceneDeckThickness(element(1, 'support', deckShape, { kind: 'bridge' }), RESOLUTION)).toBeCloseTo(
      RESOLUTION * SCENE_BRIDGE.thicknessIpari,
    );
    expect(sceneDeckThickness(element(2, 'support', deckShape, { kind: 'bridge', height: 16 }), RESOLUTION)).toBe(16);
  });

  it('мост, съезд и лестница — настил, остальные опоры — нет', () => {
    expect(isSceneDeck(element(1, 'support', deckShape, { kind: 'bridge' }))).toBe(true);
    expect(isSceneDeck(element(2, 'support', deckShape, { kind: 'ramp' }))).toBe(true);
    expect(isSceneDeck(element(3, 'support', deckShape, { kind: 'stairs' }))).toBe(true);
    expect(isSceneDeck(element(4, 'support', deckShape, { kind: 'ground' }))).toBe(false);
    expect(isSceneDeck(element(5, 'terrain', deckShape, { kind: 'grass' }))).toBe(false);
  });

  it('лестница — наклонный марш, мост — нет', () => {
    expect(isSceneRamp(element(1, 'support', deckShape, { kind: 'stairs' }))).toBe(true);
    expect(isSceneRamp(element(2, 'support', deckShape, { kind: 'ramp' }))).toBe(true);
    expect(isSceneRamp(element(3, 'support', deckShape, { kind: 'bridge' }))).toBe(false);
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

  it('видит воду под мостом и считает зазор от самого низкого места', () => {
    const grass = element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 2560, h: 2560 }, { kind: 'grass' });
    const water = element(
      2,
      'terrain',
      { type: 'rect', x: 160, y: 0, w: 80, h: 2560 },
      {
        kind: 'water',
        depth: 96,
      },
    );
    const deck = element(3, 'support', deckShape, { kind: 'bridge', elevation: 0, height: 16 });

    // Вода перекрывает луг: поверхность под мостом — вода, а не берег.
    expect(sceneSurfaceUnder([200, 150], [grass, water], RESOLUTION)).toBe(-96);
    expect(sceneSurfaceUnder([120, 150], [grass, water], RESOLUTION)).toBe(0);
    // Берег под торцами выше воды, но просвет считается по воде.
    expect(sceneDeckBaseElevation(deck, [grass, water, deck], RESOLUTION)).toBe(0);
    expect(sceneDeckLowestBaseElevation(deck, [grass, water, deck], RESOLUTION)).toBe(-96);
    expect(sceneDeckClearance(deck, [grass, water, deck], RESOLUTION)).toBe(80);
  });

  it('настил над водой — поверхность под ногами, а не вода', () => {
    const grass = element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 2560, h: 2560 }, { kind: 'grass' });
    const water = element(
      2,
      'terrain',
      { type: 'rect', x: 160, y: 0, w: 80, h: 2560 },
      {
        kind: 'water',
        depth: 96,
      },
    );
    // Пол дома лежит поверх воды: под ногами пол, а не вода под ним.
    const floor = element(
      3,
      'terrain',
      { type: 'rect', x: 100, y: 100, w: 400, h: 400 },
      { kind: 'dirt', overWater: true },
    );

    expect(sceneSurfaceUnder([200, 150], [grass, water], RESOLUTION)).toBe(-96);
    expect(sceneSurfaceUnder([200, 150], [grass, water, floor], RESOLUTION)).toBe(0);
    expect(sceneSurfaceUnder([200, 150], [grass, floor], RESOLUTION)).toBe(0);
  });

  it('крыша не становится поверхностью под ногами', () => {
    const grass = element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 2560, h: 2560 }, { kind: 'grass' });
    const floor = element(2, 'terrain', { type: 'rect', x: 100, y: 100, w: 400, h: 400 }, { kind: 'dirt' });
    const roof = element(
      3,
      'support',
      { type: 'rect', x: 80, y: 80, w: 440, h: 440 },
      {
        kind: 'roof',
        elevation: 128,
        height: 16,
      },
    );

    // Накрытие висит над полом: высота под ним — пол, а не крыша.
    expect(sceneSurfaceUnder([200, 200], [grass, floor, roof], RESOLUTION)).toBe(0);
    expect(sceneSurfaceUnder([200, 200], [grass, floor], RESOLUTION)).toBe(0);
  });

  it('проходимая крыша идёт настилом и даёт поверхность на своей высоте', () => {
    const grass = element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 2560, h: 2560 }, { kind: 'grass' });
    const roof = element(
      3,
      'support',
      { type: 'rect', x: 80, y: 80, w: 440, h: 440 },
      {
        kind: 'roof',
        elevation: 128,
        height: 16,
        walkable: true,
      },
    );

    expect(isSceneDeck(roof)).toBe(true);
    expect(isWalkableRoof(roof)).toBe(true);
    // Под ногами на крыше — её верхняя поверхность, а не луг под домом.
    expect(sceneSurfaceUnder([200, 200], [grass, roof], RESOLUTION)).toBe(128);
    expect(sceneDeckClearance(roof, [grass, roof], RESOLUTION)).toBe(112);
  });

  it('обычная крыша не считается настилом', () => {
    const roof = element(3, 'support', deckShape, { kind: 'roof', elevation: 128, height: 16 });

    expect(isWalkableRoof(roof)).toBe(false);
    expect(isSceneDeck(roof)).toBe(false);
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

  it('считает наклон лестницы как марш', () => {
    const grass = element(1, 'terrain', { type: 'rect', x: 0, y: 0, w: 2560, h: 2560 }, { kind: 'grass' });
    const stairs = element(
      2,
      'support',
      { type: 'rect', x: 100, y: 100, w: 96, h: 400 },
      {
        kind: 'stairs',
        elevation: 128,
      },
    );
    const slope = sceneRampSlope(stairs, [grass, stairs], RESOLUTION);

    expect(slope?.from).toEqual([148, 100]);
    expect(slope?.to).toEqual([148, 500]);
    expect(slope?.low).toBe(0);
    expect(slope?.high).toBe(128);
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
