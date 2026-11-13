import { Shader, UniformGroup, compileHighShaderGlProgram, localUniformBitGl, roundPixelsBitGl } from 'pixi.js';
import type { Texture } from 'pixi.js';
import { WATER_NOISE_GLSL } from '@/modules/Roleplay/Scene/Service/SceneWaterShaderSource';

/**
 * Бит поверхности воды.
 *
 * Поверхность лежит в слое плоскости, над актёрами: это плёнка, через которую
 * видно дно. Нормали считаются тем же шумом, что и в теле, поэтому
 * преломление дна совпадает с бликами. Свет — константа из
 * `SCENE_WATER_LIGHT`: пока нет суток, менять его нечем.
 *
 * Кромка здесь та же, что у тела: урез гуляет шумом, накат выдвигает его на
 * берег, а пена живёт пятнами у воды и рассасывается. Сплошная полоса по
 * контуру читалась приклеенной тряпкой.
 */
const SURFACE_BIT = {
  name: 'water-surface-bit',
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
      uniform float uWashAmp;
      uniform float uWashRate;
      uniform float uWashFreq;

      uniform vec2 uSeed;
      uniform float uTime;
      uniform float uNoiseScale;
      uniform float uNoiseStretch;
      uniform float uNoiseSpeed;
      uniform float uLod;
      uniform float uSwellScale;
      uniform float uSwellSpeed;
      uniform float uSwellLod;
      uniform float uRippleScale;
      uniform float uRippleSpeed;
      uniform float uRippleLod;
      uniform float uRippleBump;
      uniform float uBump;
      uniform float uGlintScale;
      uniform float uGlintSpeed;

      uniform float uFoamScale;
      uniform float uFoamSpeed;
      uniform float uFoamWidth;
      uniform float uFoamAlpha;
      uniform float uFoamLine;
      uniform float uFoamLineAlpha;
      uniform vec3 uFoamColor;

      uniform vec3 uSun;
      uniform vec3 uSunColor;
      uniform float uSheenShine;
      uniform float uSheenIntensity;
      uniform float uGlintShine;
      uniform float uGlintIntensity;
      uniform vec3 uSkyColor;
      uniform float uSkyIntensity;
      uniform float uBaseAlpha;

      ${WATER_NOISE_GLSL}
    `,
    main: `
      vec2 fieldUv = (vWorld - uFieldRect.xy) / uFieldRect.zw;
      vec4 field = texture2D(uField, fieldUv);
      float shore = (field.r - 0.5) * 2.0 * uDepthScale;

      // Урез гуляет тем же шумом, что и в теле, поэтому вода и поверхность
      // не расходятся на пиксель.
      float phase = waterWashPhase(vChannel, uSeed, uTime, uWashRate, uWashFreq, uEdgeScale);
      float wash = waterWashValue(phase);
      float surge = waterSurge(phase);
      float wobble = waterEdgeShift(vChannel, uSeed, uTime, uEdgeScale, uEdgeSpeed, uEdgeAmp, uEdgeFineAmp);
      float edgeDist = shore + wobble + uWashAmp * wash;
      float waterMask = smoothstep(-uEdgeSoft, uEdgeSoft, edgeDist);
      if (waterMask < 0.004) discard;

      // uLod гасит волны, когда их тайл становится мельче пикселя: иначе
      // в отдалении блики превращаются в звёздную пыль.
      vec2 rippleSlope = waterSlope(vChannel, uSeed + 71.3, uTime, uRippleScale, 1.0, uRippleSpeed) * uRippleLod;
      vec2 slope = waterSlope(vChannel, uSeed, uTime, uNoiseScale, uNoiseStretch, uNoiseSpeed) * uLod
        + waterSlope(vChannel, uSeed + 31.7, uTime, uSwellScale, uNoiseStretch, uSwellSpeed) * uSwellLod
        + rippleSlope;
      // Мелкая рябь наклоняет нормали сильнее: именно она даёт быстрые искры.
      vec3 normal = normalize(vec3(-slope * (uBump + uRippleBump * uRippleLod), 1.0));

      vec3 halfDir = normalize(normalize(uSun) + vec3(0.0, 0.0, 1.0));
      float flatFacing = halfDir.z;
      float facing = max(dot(normal, halfDir), 0.0);

      // Широкая доля — только от мелкой ряби. От крупной зыби она давала
      // облака в пол-реки, поэтому её тут нет.
      vec3 rippleNormal = normalize(vec3(-rippleSlope * uRippleBump, 1.0));
      float rippleFacing = max(dot(rippleNormal, halfDir), 0.0);
      float sheen = max(0.0, pow(rippleFacing, uSheenShine) - pow(flatFacing, uSheenShine)) * uSheenIntensity;

      // Искры: произведение двух движущихся масок. Каждая искра вспыхивает и
      // гаснет, а не плывёт светлой точкой через всю реку.
      vec2 sparklePoint = vec2(vChannel.x * uGlintScale, vChannel.y * uGlintScale)
        - vec2(uTime * uGlintSpeed, 0.0) + uSeed + 11.3;
      vec2 sparklePoint2 = sparklePoint * 1.7 + vec2(3.1, 7.7) - vec2(uTime * uGlintSpeed * 0.6, 0.0);
      float sparkle = smoothstep(0.4, 0.6, waterFbm(sparklePoint))
        * smoothstep(0.38, 0.58, waterFbm(sparklePoint2));
      float glint = pow(facing, uGlintShine) * sparkle * uGlintIntensity;

      // Пена: пятна у кромки, которые рождает накат. Импульс surge поднимает
      // их на гребне и гасит на откате, поэтому спокойная кромка остаётся
      // чистой, а каждый накат даёт свой всплеск.
      float foamNoise = waterFbm(
        vec2(vChannel.x, vChannel.y * 2.0) * uFoamScale - vec2(uTime * uFoamSpeed, 0.0) + uSeed * 3.7
      );
      float foamPatch = smoothstep(0.42, 0.62, foamNoise);
      float foamEnergy = surge * (0.35 + 0.65 * wash);
      float foamBand = 1.0 - smoothstep(0.0, uFoamWidth * (0.6 + 0.4 * wash), edgeDist);
      // Слабая линия у самой воды: урез должен читаться и без пятен, но на
      // спокойной кромке она почти не видна.
      float foamLine = 1.0 - smoothstep(0.0, uFoamLine, abs(edgeDist));
      float foam = foamPatch * foamBand * foamEnergy * 0.9
        + foamLine * (0.2 + 0.8 * foamPatch) * (0.1 + 0.9 * foamEnergy) * uFoamLineAlpha;
      foam = clamp(foam * uFoamAlpha * (0.7 + 0.6 * wash), 0.0, 1.0);

      // Фейковое небо: чем круче наклон, тем больше неба в отражении.
      float fresnel = pow(1.0 - normal.z, 3.0);
      float sky = uSkyIntensity * (0.25 + 0.75 * fresnel);

      vec3 film = uSkyColor * sky;
      // Искры идут вне альфы: в альфе блик становится непрозрачным белым
      // пятном и читается пеной, а не светом.
      float alpha = clamp(uBaseAlpha + fresnel * 0.12, 0.0, 1.0) * waterMask;
      vec3 foamColor = uFoamColor * foam;
      outColor = vec4(
        film * alpha + uSunColor * (sheen + glint) + foamColor,
        clamp(alpha + foam, 0.0, 1.0)
      );
    `,
  },
};

/** Параметры поверхности воды: считаются при сборке сцены. */
export interface SceneWaterSurfaceOptions {
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
  /** Амплитуда наката в единицах сцены. */
  washAmp: number;
  /** Частота наката, радиан в секунду. */
  washRate: number;
  /** Частота волны наката вдоль русла, радиан на единицу сцены. */
  washFreq: number;
  /** Сдвиг узоров: разводит водоёмы между собой. */
  seed: [number, number];
  /** Сколько ячеек шума приходится на единицу сцены. */
  noiseScale: number;
  /** Сжатие шума поперёк русла. */
  noiseStretch: number;
  /** Снос шума вдоль русла, ячеек в секунду. */
  noiseSpeed: number;
  /** 0…1: насколько мелкая рябь видна на текущем зуме. */
  lod: number;
  /** Сколько ячеек крупной зыби на единицу сцены. */
  swellScale: number;
  /** Снос крупной зыби вдоль русла, ячеек в секунду. */
  swellSpeed: number;
  /** 0…1: насколько крупная зыбь видна на текущем зуме. */
  swellLod: number;
  /** Сколько ячеек мелкой ряби на единицу сцены. */
  rippleScale: number;
  /** Снос мелкой ряби вдоль русла, ячеек в секунду. */
  rippleSpeed: number;
  /** 0…1: насколько мелкая рябь видна на текущем зуме. */
  rippleLod: number;
  /** Добавка к наклону нормалей от мелкой ряби. */
  rippleBump: number;
  /** Наклон нормалей: больше — злее рябь и ярче блики. */
  bump: number;
  /** Сколько ячеек искр на единицу сцены. */
  glintScale: number;
  /** Снос искр вдоль русла, ячеек в секунду. */
  glintSpeed: number;
  /** Сколько ячеек пятен пены на единицу сцены. */
  foamScale: number;
  /** Снос пятен пены вдоль русла, ячеек в секунду. */
  foamSpeed: number;
  /** Ширина полосы пены от уреза, единицы сцены. */
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
  /** Резкость широкой доли блика. */
  sheenShine: number;
  /** Яркость широкой доли блика. */
  sheenIntensity: number;
  /** Резкость искр. */
  glintShine: number;
  /** Яркость искр. */
  glintIntensity: number;
  /** Цвет неба в отражении. */
  skyColor: readonly [number, number, number];
  /** Доля неба в поверхности. */
  skyIntensity: number;
  /** Базовая прозрачность плёнки воды. */
  baseAlpha: number;
}

/**
 * Шейдер поверхности воды.
 *
 * Одна программа на все реки, свои юниформы — у каждого водоёма.
 */
export class SceneWaterSurfaceShader {
  private readonly program = compileHighShaderGlProgram({
    name: 'water-surface',
    bits: [localUniformBitGl, roundPixelsBitGl, SURFACE_BIT],
  });

  /** Поверхность водоёма: шейдер и обновление времени. */
  create(options: SceneWaterSurfaceOptions): { shader: Shader; update(seconds: number): void } {
    const uniforms = new UniformGroup({
      uFieldRect: { value: new Float32Array(options.fieldRect), type: 'vec4<f32>' },
      uDepthScale: { value: options.depthScale, type: 'f32' },
      uEdgeScale: { value: options.edgeScale, type: 'f32' },
      uEdgeSpeed: { value: options.edgeSpeed, type: 'f32' },
      uEdgeAmp: { value: options.edgeAmp, type: 'f32' },
      uEdgeFineAmp: { value: options.edgeFineAmp, type: 'f32' },
      uEdgeSoft: { value: options.edgeSoft, type: 'f32' },
      uWashAmp: { value: options.washAmp, type: 'f32' },
      uWashRate: { value: options.washRate, type: 'f32' },
      uWashFreq: { value: options.washFreq, type: 'f32' },
      uSeed: { value: new Float32Array(options.seed), type: 'vec2<f32>' },
      uTime: { value: 0, type: 'f32' },
      uNoiseScale: { value: options.noiseScale, type: 'f32' },
      uNoiseStretch: { value: options.noiseStretch, type: 'f32' },
      uNoiseSpeed: { value: options.noiseSpeed, type: 'f32' },
      uLod: { value: options.lod, type: 'f32' },
      uSwellScale: { value: options.swellScale, type: 'f32' },
      uSwellSpeed: { value: options.swellSpeed, type: 'f32' },
      uSwellLod: { value: options.swellLod, type: 'f32' },
      uRippleScale: { value: options.rippleScale, type: 'f32' },
      uRippleSpeed: { value: options.rippleSpeed, type: 'f32' },
      uRippleLod: { value: options.rippleLod, type: 'f32' },
      uRippleBump: { value: options.rippleBump, type: 'f32' },
      uBump: { value: options.bump, type: 'f32' },
      uGlintScale: { value: options.glintScale, type: 'f32' },
      uGlintSpeed: { value: options.glintSpeed, type: 'f32' },
      uFoamScale: { value: options.foamScale, type: 'f32' },
      uFoamSpeed: { value: options.foamSpeed, type: 'f32' },
      uFoamWidth: { value: options.foamWidth, type: 'f32' },
      uFoamAlpha: { value: options.foamAlpha, type: 'f32' },
      uFoamLine: { value: options.foamLine, type: 'f32' },
      uFoamLineAlpha: { value: options.foamLineAlpha, type: 'f32' },
      uFoamColor: { value: new Float32Array(options.foamColor), type: 'vec3<f32>' },
      uSun: { value: new Float32Array(options.sun), type: 'vec3<f32>' },
      uSunColor: { value: new Float32Array(options.sunColor), type: 'vec3<f32>' },
      uSheenShine: { value: options.sheenShine, type: 'f32' },
      uSheenIntensity: { value: options.sheenIntensity, type: 'f32' },
      uGlintShine: { value: options.glintShine, type: 'f32' },
      uGlintIntensity: { value: options.glintIntensity, type: 'f32' },
      uSkyColor: { value: new Float32Array(options.skyColor), type: 'vec3<f32>' },
      uSkyIntensity: { value: options.skyIntensity, type: 'f32' },
      uBaseAlpha: { value: options.baseAlpha, type: 'f32' },
    });
    const shader = new Shader({
      glProgram: this.program,
      resources: { surfaceUniforms: uniforms, uField: options.field.source },
    });

    return {
      shader,
      update: (seconds: number): void => {
        uniforms.uniforms.uTime = seconds;
      },
    };
  }
}
