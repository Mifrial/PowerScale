import { Container, Texture, TextureSource } from 'pixi.js';
import { describe, expect, it } from 'vitest';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneTerrainBlendOptions } from '@/modules/Roleplay/Scene/Dto/SceneTerrainBlendOptions';
import { SceneTerrainBlendService } from '@/modules/Roleplay/Scene/Service/SceneTerrainBlendService';
import { SceneTerrainBlendShader } from '@/modules/Roleplay/Scene/Service/SceneTerrainBlendShader';

type Band = NonNullable<ReturnType<SceneTerrainBlendService['band']>>;

const square = (size: number): ScenePoint[] => [
  [0, 0],
  [size, 0],
  [size, size],
  [0, size],
];

const blendOptions = (): SceneTerrainBlendOptions => ({
  material: Texture.WHITE,
  period: 64,
  offset: [0, 0],
  macro: null,
  macroPeriod: 320,
  macroOffset: [0, 0],
  macroMix: 0,
  overlay: null,
  overlayPeriod: 32,
  overlayOffset: [0, 0],
  overlayMix: 0,
  solid: 0,
  ragged: 4,
  base: 0.35,
  amp: 0.65,
  noiseScale: 0.05,
  fineScale: 0.2,
  fineAmp: 0.5,
  soft: 0.5,
  alpha: 1,
  seed: [1, 2],
});

const outerPoints = (band: Band): ScenePoint[] =>
  Array.from({ length: band.positions.length / 4 }, (_, index) => [
    band.positions[index * 4 + 2],
    band.positions[index * 4 + 3],
  ]);

describe('SceneTerrainBlendService', () => {
  const service = new SceneTerrainBlendService();

  it('кладёт внутреннюю кромку на контур, а внешнюю — наружу', () => {
    const band = service.band(square(40), 4) as Band;

    expect(band.positions.length).toBe(4 * 4);
    expect(band.indices.length).toBe(4 * 6);
    for (let index = 0; index < 4; index += 1) {
      // UV поперёк полосы: 0 на контуре, 1 на внешнем краю.
      expect(band.uvs[index * 4]).toBe(0);
      expect(band.uvs[index * 4 + 2]).toBe(1);
    }

    for (const [x, y] of outerPoints(band)) {
      expect(x < 0 || x > 40 || y < 0 || y > 40).toBe(true);
    }
  });

  it('держит направление наружу при обратном обходе контура', () => {
    const band = service.band([...square(40)].reverse(), 4) as Band;

    for (const [x, y] of outerPoints(band)) {
      expect(x < 0 || x > 40 || y < 0 || y > 40).toBe(true);
    }
  });

  it('выносит внешнюю кромку на заданную ширину', () => {
    const outline: ScenePoint[] = [
      [0, 0],
      [40, 0],
      [40, 20],
      [40, 40],
      [0, 40],
    ];
    const band = service.band(outline, 4) as Band;
    // На середине стороны митра не растягивает полосу: вынос равен ширине.
    expect(band.positions[2 * 4 + 2]).toBeCloseTo(44);
    expect(band.positions[2 * 4 + 3]).toBeCloseTo(20);
  });

  it('не строит полосу по вырожденному контуру', () => {
    expect(
      service.band(
        [
          [0, 0],
          [1, 1],
        ],
        4,
      ),
    ).toBeNull();
    expect(
      service.band(
        [
          [0, 0],
          [1, 0],
          [2, 0],
        ],
        4,
      ),
    ).toBeNull();
    expect(service.band(square(40), 0)).toBeNull();
  });

  it('добавляет полосу в слой', () => {
    const layer = new Container();
    const mesh = service.spill(layer, square(40), blendOptions());

    expect(mesh).not.toBeNull();
    expect(layer.children).toContain(mesh);
  });
});

describe('SceneTerrainBlendShader', () => {
  it('биндит материал и крупную вариацию как TextureSource', () => {
    const shader = new SceneTerrainBlendShader().create(blendOptions());

    // GL-синхронизация Pixi биндит только TextureSource и TextureView:
    // обёртка Texture молча пропускается, и сэмплер остаётся пустым.
    expect(shader.resources.uMaterial).toBeInstanceOf(TextureSource);
    expect(shader.resources.uMacro).toBeInstanceOf(TextureSource);
    expect(shader.resources.uOverlay).toBeInstanceOf(TextureSource);
  });

  it('без крупной вариации подставляет материал вместо макро-текстуры', () => {
    const shader = new SceneTerrainBlendShader().create(blendOptions());

    expect(shader.resources.uMacro).toBe(shader.resources.uMaterial);
    expect(shader.resources.uOverlay).toBe(shader.resources.uMaterial);
  });
});
