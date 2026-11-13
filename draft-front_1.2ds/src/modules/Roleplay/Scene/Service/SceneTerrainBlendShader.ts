import { Shader, UniformGroup, compileHighShaderGlProgram, localUniformBitGl, roundPixelsBitGl } from 'pixi.js';
import type { SceneTerrainBlendOptions } from '@/modules/Roleplay/Scene/Dto/SceneTerrainBlendOptions';

/**
 * Шум рваной кромки.
 *
 * Кромка террейна — не линия, а язык материала, поэтому край рвётся крупным
 * fbm, а мелкая октава даёт зерно на самом краю. Это отдельный шум, а не
 * водный: водный сдвигается временем, а кромка должна стоять на месте.
 */
const TERRAIN_BLEND_NOISE_GLSL = `
  float terrainBlendHash(vec2 point) {
    vec3 p3 = fract(vec3(point.xyx) * 0.1031);
    p3 += dot(p3, p3.yzx + 33.33);
    return fract((p3.x + p3.y) * p3.z);
  }

  float terrainBlendNoise(vec2 point) {
    vec2 cell = floor(point);
    vec2 local = fract(point);
    vec2 weight = local * local * (3.0 - 2.0 * local);
    float a = terrainBlendHash(cell);
    float b = terrainBlendHash(cell + vec2(1.0, 0.0));
    float c = terrainBlendHash(cell + vec2(0.0, 1.0));
    float d = terrainBlendHash(cell + vec2(1.0, 1.0));
    return mix(mix(a, b, weight.x), mix(c, d, weight.x), weight.y);
  }

  float terrainBlendFbm(vec2 point) {
    float value = terrainBlendNoise(point) * 0.5;
    value += terrainBlendNoise(point * 2.07 + vec2(13.7, 7.1)) * 0.3;
    value += terrainBlendNoise(point * 4.13 + vec2(3.1, 19.3)) * 0.2;
    return value;
  }
`;

/**
 * Бит рваной кромки.
 *
 * Полоса лежит вдоль контура фигуры: `vBand.x` — 0 на контуре и 1 на внешнем
 * краю. Материал семплится по мировым координатам с тем же периодом и сдвигом,
 * что у заливки, поэтому рисунок продолжается через край без шва. Ширина языка
 * считается шумом, и альфа гаснет за ней: граница становится рваной.
 */
const BLEND_BIT = {
  name: 'terrain-blend-bit',
  vertex: {
    header: `
      out highp vec2 vWorld;
      out highp vec2 vBand;
    `,
    main: `
      vWorld = position;
      vBand = uv;
    `,
  },
  fragment: {
    header: `
      precision highp float;

      in highp vec2 vWorld;
      in highp vec2 vBand;

      uniform sampler2D uMaterial;
      uniform sampler2D uMacro;
      uniform sampler2D uOverlay;

      uniform float uPeriod;
      uniform vec2 uOffset;
      uniform float uMacroPeriod;
      uniform vec2 uMacroOffset;
      uniform float uMacroMix;
      uniform float uOverlayPeriod;
      uniform vec2 uOverlayOffset;
      uniform float uOverlayMix;

      uniform float uSolid;
      uniform float uRagged;
      uniform float uBase;
      uniform float uAmp;
      uniform float uNoiseScale;
      uniform float uFineScale;
      uniform float uFineAmp;
      uniform float uSoft;
      uniform float uAlpha;
      uniform vec2 uSeed;

      ${TERRAIN_BLEND_NOISE_GLSL}
    `,
    main: `
      // Расстояние от контура в единицах сцены: полоса уже разложена по
      // ширине геометрией, здесь остаётся только её развернуть.
      float dist = vBand.x * (uSolid + uRagged);
      float coarse = terrainBlendFbm(vWorld * uNoiseScale + uSeed);
      float fine = terrainBlendNoise(vWorld * uFineScale + uSeed * 1.7);
      float ragged = uRagged * (uBase + uAmp * coarse) * (1.0 + (fine - 0.5) * uFineAmp);
      float edge = uSolid + ragged;
      float alpha = 1.0 - smoothstep(edge - uSoft, edge + uSoft, dist);
      if (alpha < 0.004) discard;

      vec3 color = texture2D(uMaterial, (vWorld - uOffset) / uPeriod).rgb;
      vec4 macro = texture2D(uMacro, (vWorld - uMacroOffset) / uMacroPeriod);
      color = mix(color, macro.rgb, macro.a * uMacroMix);
      vec4 overlay = texture2D(uOverlay, (vWorld - uOverlayOffset) / uOverlayPeriod);
      color = mix(color, overlay.rgb, overlay.a * uOverlayMix);

      // Премноженное альфа: Pixi смешивает premultiplied alpha.
      outColor = vec4(color * alpha * uAlpha, alpha * uAlpha);
    `,
  },
};

/** Шейдер рваной кромки террейна. */
export class SceneTerrainBlendShader {
  private readonly program = compileHighShaderGlProgram({
    name: 'terrain-blend',
    bits: [localUniformBitGl, roundPixelsBitGl, BLEND_BIT],
  });

  /** Шейдер полосы: материал, шум и геометрия кромки. */
  create(options: SceneTerrainBlendOptions): Shader {
    const uniforms = new UniformGroup({
      uPeriod: { value: options.period, type: 'f32' },
      uOffset: { value: new Float32Array(options.offset), type: 'vec2<f32>' },
      uMacroPeriod: { value: options.macroPeriod, type: 'f32' },
      uMacroOffset: { value: new Float32Array(options.macroOffset), type: 'vec2<f32>' },
      uMacroMix: { value: options.macroMix, type: 'f32' },
      uOverlayPeriod: { value: options.overlayPeriod, type: 'f32' },
      uOverlayOffset: { value: new Float32Array(options.overlayOffset), type: 'vec2<f32>' },
      uOverlayMix: { value: options.overlayMix, type: 'f32' },
      uSolid: { value: options.solid, type: 'f32' },
      uRagged: { value: options.ragged, type: 'f32' },
      uBase: { value: options.base, type: 'f32' },
      uAmp: { value: options.amp, type: 'f32' },
      uNoiseScale: { value: options.noiseScale, type: 'f32' },
      uFineScale: { value: options.fineScale, type: 'f32' },
      uFineAmp: { value: options.fineAmp, type: 'f32' },
      uSoft: { value: options.soft, type: 'f32' },
      uAlpha: { value: options.alpha, type: 'f32' },
      uSeed: { value: new Float32Array(options.seed), type: 'vec2<f32>' },
    });

    return new Shader({
      glProgram: this.program,
      resources: {
        blendUniforms: uniforms,
        uMaterial: options.material.source,
        uMacro: (options.macro ?? options.material).source,
        uOverlay: (options.overlay ?? options.material).source,
      },
    });
  }
}
