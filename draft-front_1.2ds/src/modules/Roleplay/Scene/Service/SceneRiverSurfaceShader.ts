import { Shader, UniformGroup, compileHighShaderGlProgram, localUniformBitGl, roundPixelsBitGl } from 'pixi.js';
import type { Texture } from 'pixi.js';
import { WATER_NOISE_GLSL } from '@/modules/Roleplay/Scene/Service/SceneWaterShaderSource';

/**
 * Бит поверхности реки.
 *
 * Рябь — не раскраска, а процедурная карта нормалей: три направленные волны
 * дают наклон, по нему считается блик Блинна-Фонга и преломление дна в теле.
 * Скорость волн зависит от расстояния до осевой: центр русла быстрее, у
 * берегов вода тормозит. Пена — текстура `water_foam` вдоль уреза, которую
 * рвёт шумовая маска; наката на берег у реки нет.
 */
const SURFACE_BIT = {
  name: 'water-river-surface-bit',
  vertex: {
    header: `
      out highp vec2 vWorld;
      out highp vec2 vChannel;
    `,
    main: `
      vWorld = position;
      vChannel = uv;
    `,
  },
  fragment: {
    header: `
      precision highp float;

      in highp vec2 vWorld;
      in highp vec2 vChannel;

      uniform sampler2D uField;
      uniform vec4 uFieldRect;
      uniform float uDepthScale;

      uniform float uEdgeScale;
      uniform float uEdgeSpeed;
      uniform float uEdgeAmp;
      uniform float uEdgeFineAmp;
      uniform float uEdgeSoft;

      uniform vec2 uSeed;
      uniform float uTime;

      uniform float uFlowBank;
      uniform float uFlowCenter;
      uniform vec4 uWaveA;
      uniform vec4 uWaveB;
      uniform vec4 uWaveC;
      uniform float uWaveEps;
      uniform float uWaveBump;
      uniform float uWaveLod;
      uniform float uNoiseScaleA;
      uniform float uNoiseSpeedA;
      uniform float uNoiseStretchA;
      uniform float uNoiseWeightA;
      uniform float uNoiseScaleB;
      uniform float uNoiseSpeedB;
      uniform float uNoiseStretchB;
      uniform float uNoiseWeightB;

      uniform float uSparkleScale;
      uniform float uSparkleSpeed;
      uniform float uSparklePulse;
      uniform float uSparkleIntensity;
      uniform float uGlintShine;
      uniform float uGlintIntensity;

      uniform sampler2D uFoam;
      uniform float uFoamPeriod;
      uniform float uFoamDrift;
      uniform float uFoamMaskScale;
      uniform float uFoamMaskSpeed;
      uniform float uFoamWidth;
      uniform float uFoamAlpha;
      uniform float uFoamLine;
      uniform float uFoamLineAlpha;
      uniform vec3 uFoamColor;

      uniform vec3 uSun;
      uniform vec3 uSunColor;
      uniform vec3 uSkyColor;
      uniform float uSkyIntensity;
      uniform float uBaseAlpha;

      ${WATER_NOISE_GLSL}
    `,
    main: `
      vec2 fieldUv = (vWorld - uFieldRect.xy) / uFieldRect.zw;
      vec4 field = texture2D(uField, fieldUv);
      float shore = (field.r - 0.5) * 2.0 * uDepthScale;

      // Урез без наката: река не прибивает волну к берегу.
      float wobble = waterEdgeShift(vChannel, uSeed, uTime, uEdgeScale, uEdgeSpeed, uEdgeAmp, uEdgeFineAmp);
      float edgeDist = shore + wobble;
      float waterMask = smoothstep(-uEdgeSoft, uEdgeSoft, edgeDist);
      if (waterMask < 0.004) discard;

      float depth = max(edgeDist, 0.0);
      float acrossNorm = abs(vChannel.y) / max(0.0001, abs(vChannel.y) + depth);
      float flow = mix(uFlowBank, uFlowCenter, 1.0 - acrossNorm);
      // Нормаль поверхности из суммы направленных волн.
      vec2 gradient = riverGradient(vChannel, uTime, uSeed, flow, uWaveA, uWaveB, uWaveC, uWaveEps)
        * uWaveLod * uWaveBump;
      gradient += waterNoiseGradient(
        vChannel, uSeed + 3.1, uTime, flow, uNoiseScaleA, uNoiseStretchA, uNoiseSpeedA
      ) * uNoiseWeightA * uWaveLod;
      gradient += waterNoiseGradient(
        vChannel, uSeed + 17.3, uTime, flow, uNoiseScaleB, uNoiseStretchB, uNoiseSpeedB
      ) * uNoiseWeightB * uWaveLod;
      vec3 normal = normalize(vec3(-gradient, 1.0));

      vec3 halfDir = normalize(normalize(uSun) + vec3(0.0, 0.0, 1.0));
      float facing = max(dot(normal, halfDir), 0.0);
      // Высокая степень делает блики мелкими и живыми, а не белой плёнкой.
      // Две маски искр разного масштаба: блики вспыхивают и гаснут, а не
      // плывут одной светлой лентой.
      float sparkleA = smoothstep(
        0.4,
        0.6,
        waterFbm(vChannel * uSparkleScale + uSeed - vec2(uTime * uSparkleSpeed, 0.0))
      );
      float sparkleB = smoothstep(
        0.4,
        0.6,
        waterFbm(vChannel * uSparkleScale * 1.7 + uSeed - vec2(uTime * uSparkleSpeed * 1.6, 7.1))
      );
      // Широкая маска пятен: модулирует яркость плёнки. Снос — вниз по
      // течению, чтобы не спорить с волнами и пеной.
      float flicker = 0.55 + 0.45 * waterFbm(
        vChannel * uSparkleScale * 0.6 + uSeed - vec2(uTime * uSparkleSpeed * 2.0, 0.0) + vec2(0.0, 3.7)
      );
      float glint = pow(facing, uGlintShine) * uGlintIntensity * max(sparkleA, sparkleB) * flicker;

      // Мелкие искры: независимые вспышки в ячейках. Они не зависят от
      // нормалей, поэтому видны и на среднем зуме, где волна уже сглажена.
      vec2 sparklePoint = vChannel * uSparkleScale;
      float sparkleFine = sparkleCells(sparklePoint, uTime, uSparklePulse, 0.982);
      float sparks = sparkleFine * uSparkleIntensity;

      // Пена: текстура вдоль уреза. Маска рвёт полосу на пятна, поэтому она
      // не читается приклеенной «мочалкой».
      float foamV = clamp(edgeDist / uFoamWidth, -1.0, 1.0) * 0.5 + 0.5;
      float foamTex = texture2D(
        uFoam,
        vec2(vChannel.x / uFoamPeriod - uTime * uFoamDrift, foamV)
      ).a;
      float foamMask = smoothstep(
        0.45,
        0.65,
        waterFbm(vChannel * uFoamMaskScale + uSeed - vec2(uTime * uFoamMaskSpeed, 0.0))
      );
      float foamBand = 1.0 - smoothstep(0.0, uFoamWidth, abs(edgeDist));
      float foamLine = 1.0 - smoothstep(0.0, uFoamLine, abs(edgeDist));
      float foam = foamTex * foamBand * (0.15 + 0.85 * foamMask) * uFoamAlpha
        + foamLine * uFoamLineAlpha;
      foam = clamp(foam, 0.0, 1.0);

      // Фейковое небо: слабое, чтобы река не превращалась в морскую гладь.
      float fresnel = pow(1.0 - normal.z, 3.0);
      float sky = uSkyIntensity * (0.25 + 0.75 * fresnel);
      // Мерцание широких пятен: быстрая маска меняет яркость плёнки, поэтому
      // пятна не ползут ровной волной. Модуляция внутри альфы — иначе у уреза
      // появился бы светящийся ореол.
      vec3 film = uSkyColor * sky * (0.3 + 0.9 * flicker);
      float alpha = clamp(uBaseAlpha + fresnel * 0.1, 0.0, 1.0) * waterMask;
      vec3 foamColor = uFoamColor * foam;
      outColor = vec4(
        film * alpha + uSunColor * (glint + sparks) + foamColor,
        clamp(alpha + foam, 0.0, 1.0)
      );
    `,
  },
};

/** Параметры поверхности реки. */
export interface SceneRiverSurfaceOptions {
  /** Поле глубины: R — знаковая дистанция, G — силуэт. */
  field: Texture;
  /** Прямоугольник поля в единицах сцены. */
  fieldRect: [number, number, number, number];
  /** Масштаб знаковой дистанции в единицах сцены. */
  depthScale: number;
  /** Сколько ячеек шума кромки на единицу сцены. */
  edgeScale: number;
  /** Снос кромки вдоль русла, ячеек в секунду. */
  edgeSpeed: number;
  /** Крупная амплитуда кромки в единицах сцены. */
  edgeAmp: number;
  /** Мелкая амплитуда кромки в единицах сцены. */
  edgeFineAmp: number;
  /** Мягкость кромки в единицах сцены. */
  edgeSoft: number;
  /** Сдвиг узоров: разводит водоёмы между собой. */
  seed: [number, number];
  /** Скорость у берега и в центре. */
  flowBank: number;
  flowCenter: number;
  /** Волны нормалей: (масштаб, скорость, угол, амплитуда). */
  waveA: [number, number, number, number];
  waveB: [number, number, number, number];
  waveC: [number, number, number, number];
  /** Шаг конечных разностей градиента в единицах сцены. */
  waveEps: number;
  /** Наклон нормалей. */
  waveBump: number;
  /** 0…1: насколько мелкая волна видна на текущем зуме. */
  waveLod: number;
  /** Шумовые слои ряби: масштаб, скорость, сжатие и вес. */
  noiseScaleA: number;
  noiseSpeedA: number;
  noiseStretchA: number;
  noiseWeightA: number;
  noiseScaleB: number;
  noiseSpeedB: number;
  noiseStretchB: number;
  noiseWeightB: number;
  /** Сколько ячеек маски искр на единицу сцены. */
  sparkleScale: number;
  /** Снос маски искр, ячеек в секунду. */
  sparkleSpeed: number;
  /** Частота вспышек ячеек искр, герц. */
  sparklePulse: number;
  /** Яркость мелких искр. */
  sparkleIntensity: number;
  /** Резкость солнечного блика. */
  glintShine: number;
  /** Яркость солнечного блика. */
  glintIntensity: number;
  /** Текстура пены. */
  foam: Texture;
  /** Тайл пены вдоль русла, единицы сцены. */
  foamPeriod: number;
  /** Снос пены вдоль русла, доли тайла в секунду. */
  foamDrift: number;
  /** Сколько ячеек маски пены на единицу сцены. */
  foamMaskScale: number;
  /** Снос маски пены, ячеек в секунду. */
  foamMaskSpeed: number;
  /** Ширина пенной полосы от уреза, единицы сцены. */
  foamWidth: number;
  /** Плотность пены. */
  foamAlpha: number;
  /** Ширина слабой линии у уреза, единицы сцены. */
  foamLine: number;
  /** Плотность слабой линии. */
  foamLineAlpha: number;
  /** Цвет пены. */
  foamColor: readonly [number, number, number];
  /** Направление на солнце. */
  sun: readonly [number, number, number];
  /** Цвет блика. */
  sunColor: readonly [number, number, number];
  /** Цвет неба в отражении. */
  skyColor: readonly [number, number, number];
  /** Доля неба в поверхности. */
  skyIntensity: number;
  /** Базовая прозрачность плёнки воды. */
  baseAlpha: number;
}

/** Шейдер поверхности реки. */
export class SceneRiverSurfaceShader {
  private readonly program = compileHighShaderGlProgram({
    name: 'water-river-surface',
    bits: [localUniformBitGl, roundPixelsBitGl, SURFACE_BIT],
  });

  /** Поверхность реки: шейдер и обновление времени. */
  create(options: SceneRiverSurfaceOptions): { shader: Shader; update(seconds: number): void } {
    const uniforms = new UniformGroup({
      uFieldRect: { value: new Float32Array(options.fieldRect), type: 'vec4<f32>' },
      uDepthScale: { value: options.depthScale, type: 'f32' },
      uEdgeScale: { value: options.edgeScale, type: 'f32' },
      uEdgeSpeed: { value: options.edgeSpeed, type: 'f32' },
      uEdgeAmp: { value: options.edgeAmp, type: 'f32' },
      uEdgeFineAmp: { value: options.edgeFineAmp, type: 'f32' },
      uEdgeSoft: { value: options.edgeSoft, type: 'f32' },
      uSeed: { value: new Float32Array(options.seed), type: 'vec2<f32>' },
      uTime: { value: 0, type: 'f32' },
      uFlowBank: { value: options.flowBank, type: 'f32' },
      uFlowCenter: { value: options.flowCenter, type: 'f32' },
      uWaveA: { value: new Float32Array(options.waveA), type: 'vec4<f32>' },
      uWaveB: { value: new Float32Array(options.waveB), type: 'vec4<f32>' },
      uWaveC: { value: new Float32Array(options.waveC), type: 'vec4<f32>' },
      uWaveEps: { value: options.waveEps, type: 'f32' },
      uWaveBump: { value: options.waveBump, type: 'f32' },
      uWaveLod: { value: options.waveLod, type: 'f32' },
      uNoiseScaleA: { value: options.noiseScaleA, type: 'f32' },
      uNoiseSpeedA: { value: options.noiseSpeedA, type: 'f32' },
      uNoiseStretchA: { value: options.noiseStretchA, type: 'f32' },
      uNoiseWeightA: { value: options.noiseWeightA, type: 'f32' },
      uNoiseScaleB: { value: options.noiseScaleB, type: 'f32' },
      uNoiseSpeedB: { value: options.noiseSpeedB, type: 'f32' },
      uNoiseStretchB: { value: options.noiseStretchB, type: 'f32' },
      uNoiseWeightB: { value: options.noiseWeightB, type: 'f32' },
      uSparkleScale: { value: options.sparkleScale, type: 'f32' },
      uSparkleSpeed: { value: options.sparkleSpeed, type: 'f32' },
      uSparklePulse: { value: options.sparklePulse, type: 'f32' },
      uSparkleIntensity: { value: options.sparkleIntensity, type: 'f32' },
      uGlintShine: { value: options.glintShine, type: 'f32' },
      uGlintIntensity: { value: options.glintIntensity, type: 'f32' },
      uFoamPeriod: { value: options.foamPeriod, type: 'f32' },
      uFoamDrift: { value: options.foamDrift, type: 'f32' },
      uFoamMaskScale: { value: options.foamMaskScale, type: 'f32' },
      uFoamMaskSpeed: { value: options.foamMaskSpeed, type: 'f32' },
      uFoamWidth: { value: options.foamWidth, type: 'f32' },
      uFoamAlpha: { value: options.foamAlpha, type: 'f32' },
      uFoamLine: { value: options.foamLine, type: 'f32' },
      uFoamLineAlpha: { value: options.foamLineAlpha, type: 'f32' },
      uFoamColor: { value: new Float32Array(options.foamColor), type: 'vec3<f32>' },
      uSun: { value: new Float32Array(options.sun), type: 'vec3<f32>' },
      uSunColor: { value: new Float32Array(options.sunColor), type: 'vec3<f32>' },
      uSkyColor: { value: new Float32Array(options.skyColor), type: 'vec3<f32>' },
      uSkyIntensity: { value: options.skyIntensity, type: 'f32' },
      uBaseAlpha: { value: options.baseAlpha, type: 'f32' },
    });
    const shader = new Shader({
      glProgram: this.program,
      resources: {
        surfaceUniforms: uniforms,
        uField: options.field.source,
        uFoam: options.foam.source,
      },
    });

    return {
      shader,
      update: (seconds: number): void => {
        uniforms.uniforms.uTime = seconds;
      },
    };
  }
}
