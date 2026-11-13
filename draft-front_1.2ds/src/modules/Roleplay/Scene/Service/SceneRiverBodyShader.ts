import { Shader, UniformGroup, compileHighShaderGlProgram, localUniformBitGl, roundPixelsBitGl } from 'pixi.js';
import type { Texture } from 'pixi.js';
import { WATER_CAUSTICS_GLSL, WATER_NOISE_GLSL } from '@/modules/Roleplay/Scene/Service/SceneWaterShaderSource';

/**
 * Бит тела реки.
 *
 * Река — не спокойная вода: у неё есть русло и течение. Дно — песок, на
 * котором пятнами рассыпана галька; к урезу галька гаснет в песок. Нормали
 * поверхности считаются направленными волнами, поэтому преломление дна
 * двигается вместе с рябью. Наката на берег нет: кромка спокойная, но
 * неровная, а мокрота и пена живут вдоль уреза.
 */
const BODY_BIT = {
  name: 'water-river-body-bit',
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
      uniform sampler2D uBed;
      uniform sampler2D uSand;

      uniform vec4 uFieldRect;
      uniform float uDepthScale;

      uniform float uEdgeScale;
      uniform float uEdgeSpeed;
      uniform float uEdgeAmp;
      uniform float uEdgeFineAmp;
      uniform float uEdgeSoft;

      uniform float uWetWidth;
      uniform float uWetScale;
      uniform vec3 uWetColor;
      uniform float uWetAlpha;
      uniform vec3 uSpillColor;
      uniform float uSpillMix;

      uniform float uBedPeriod;
      uniform vec2 uBedShift;
      uniform vec3 uBedTint;
      uniform float uSandPeriod;
      uniform vec2 uSandShift;
      uniform float uPebbleScale;
      uniform float uPebbleBase;
      uniform float uPebbleMix;
      uniform float uBedFade;

      uniform vec3 uShallowColor;
      uniform float uShallowMix;
      uniform vec3 uDeepColor;
      uniform float uAbsorb;

      uniform vec2 uSeed;
      uniform float uTime;

      uniform float uFlowBank;
      uniform float uFlowCenter;
      uniform vec4 uWaveA;
      uniform vec4 uWaveB;
      uniform vec4 uWaveC;
      uniform float uWaveEps;
      uniform float uWaveBump;
      uniform float uWaveShade;
      uniform float uWaveLod;
      uniform float uNoiseScaleA;
      uniform float uNoiseSpeedA;
      uniform float uNoiseStretchA;
      uniform float uNoiseWeightA;
      uniform float uNoiseScaleB;
      uniform float uNoiseSpeedB;
      uniform float uNoiseStretchB;
      uniform float uNoiseWeightB;
      uniform float uShadeScale;
      uniform float uShadeSpeed;
      uniform float uFlickerScale;
      uniform float uFlickerSpeed;
      uniform float uFlickerMix;
      uniform float uShoal;
      uniform float uRefraction;

      uniform float uCausticsScale;
      uniform float uCausticsLine;
      uniform float uCausticsAlpha;
      uniform float uCausticsLod;
      uniform vec3 uCausticsColor;
      uniform vec2 uCausticsFlow;
      uniform float uCausticsShift;
      uniform float uCausticsStretch;

      uniform float uCausticsScale2;
      uniform float uCausticsLine2;
      uniform float uCausticsAlpha2;
      uniform float uCausticsLod2;
      uniform vec2 uCausticsFlow2;
      uniform float uCausticsAngle2;
      uniform float uCausticsDetail;
      uniform float uCausticsLens;

      uniform float uDeepMix;
      uniform float uPoolScale;
      uniform float uPoolMix;

      uniform float uToneScale;
      uniform float uToneSpeed;
      uniform float uToneMix;

      ${WATER_NOISE_GLSL}
      ${WATER_CAUSTICS_GLSL}
    `,
    main: `
      vec2 fieldUv = (vWorld - uFieldRect.xy) / uFieldRect.zw;
      vec4 field = texture2D(uField, fieldUv);
      float shore = (field.r - 0.5) * 2.0 * uDepthScale;

      // Кромка без наката: река не прибивает волну к берегу. Урез только
      // гуляет крупным шумом, поэтому край неровный, но спокойный.
      float wobble = waterEdgeShift(vChannel, uSeed, uTime, uEdgeScale, uEdgeSpeed, uEdgeAmp, uEdgeFineAmp);
      float edgeDist = shore + wobble;
      float waterMask = smoothstep(-uEdgeSoft, uEdgeSoft, edgeDist);

      // Мокрый песок: ровная мягкая полоса вдоль уреза. Пятнистость давала
      // отдельные тёмные кляксы вдали от воды.
      float wetBand = 1.0 - smoothstep(0.0, uWetWidth, -edgeDist);
      float wet = wetBand * (1.0 - waterMask) * 0.85;
      if (waterMask < 0.004 && wet < 0.004) discard;

      float depth = max(edgeDist, 0.0);
      // Профиль течения: у берега медленнее, в центре быстрее. Расстояние до
      // осевой плюс расстояние до берега дают локальную полуширину русла.
      float acrossNorm = abs(vChannel.y) / max(0.0001, abs(vChannel.y) + depth);
      float flow = mix(uFlowBank, uFlowCenter, 1.0 - acrossNorm);
      vec2 gradient = riverGradient(vChannel, uTime, uSeed, flow, uWaveA, uWaveB, uWaveC, uWaveEps)
        * uWaveLod * uWaveBump;
      // Шумовая рябь ломает ровные полосы направленных волн: без неё русло
      // читается полосатым, как расчёска.
      gradient += waterNoiseGradient(
        vChannel, uSeed + 3.1, uTime, flow, uNoiseScaleA, uNoiseStretchA, uNoiseSpeedA
      ) * uNoiseWeightA * uWaveLod;
      gradient += waterNoiseGradient(
        vChannel, uSeed + 17.3, uTime, flow, uNoiseScaleB, uNoiseStretchB, uNoiseSpeedB
      ) * uNoiseWeightB * uWaveLod;
      float shade = waterFbm(
        vec2(vChannel.x, vChannel.y * 1.4) * uShadeScale - vec2(uTime * uShadeSpeed, 0.0) + uSeed * 4.7
      ) - 0.5;

      // У уреза преломление гаснет: иначе дно на кромке вылезает на берег.
      // Ширина этой зоны — отдельная величина, иначе сила преломления
      // растягивала бы и её.
      float shoal = smoothstep(0.0, uShoal, depth);
      vec2 refracted = vWorld + gradient * uRefraction * shoal;

      // Песок продолжается под водой тем же тайлом, что и на берегу: на нём
      // пятнами рассыпана галька. К урезу галька гаснет в песок.
      vec2 sandUv = refracted / uSandPeriod + uSandShift;
      vec3 sand = texture2D(uSand, sandUv).rgb;
      vec2 bedUv = refracted / uBedPeriod + uBedShift;
      vec3 pebbles = texture2D(uBed, bedUv).rgb * uBedTint;
      float pebblePatch = waterFbm(vec2(vChannel.x, vChannel.y * 0.8) * uPebbleScale + uSeed * 6.1);
      float pebbleMask = mix(uPebbleBase, uPebbleMix, smoothstep(0.32, 0.68, pebblePatch));
      pebbleMask *= smoothstep(0.0, uBedFade, edgeDist);
      vec3 bed = mix(sand, pebbles, pebbleMask);

      // Поглощение: на мели видно дно, к середине цвет уходит в толщу.
      float absorb = 1.0 - exp(-depth / uAbsorb);
      float pool = waterFbm(vec2(vChannel.x * uPoolScale, vChannel.y * uPoolScale * 0.5) + uSeed);
      absorb = min(absorb * mix(1.0 - uPoolMix, 1.0 + uPoolMix, pool), uDeepMix);
      vec3 shallow = mix(bed, uShallowColor, uShallowMix);
      vec3 color = mix(shallow, uDeepColor, absorb);
      // Шумовая модуляция оттенка: не полосы, а живые пятна тона.
      float shadeFlicker = 0.55 + 0.45 * waterFbm(
        vChannel * uShadeScale * 2.5 + uSeed - vec2(uTime * uShadeSpeed * 3.0, 5.1)
      );
      float shadeValue = shade * shadeFlicker;
      vec3 water = color * (1.0 + shadeValue * uWaveShade);

      // Мягкие зоны тона: не плывут по реке, а появляются и исчезают на месте.
      vec2 tonePoint = vec2(vChannel.x, vChannel.y * 0.8) * uToneScale + uSeed * 4.3;
      float toneA = waterFbm(tonePoint - vec2(uTime * uToneSpeed, 0.0));
      float toneB = waterFbm(tonePoint * 1.7 + vec2(7.1, 3.3) - vec2(uTime * uToneSpeed * 0.6, 0.0));
      float tone = smoothstep(0.3, 0.65, toneA * 0.6 + toneB * 0.4);
      water *= 1.0 + (tone - 0.5) * uToneMix;

      // Пятна на непрозрачном теле: обычный снос вниз по течению.
      float flicker = 0.55 + 0.45 * waterFbm(
        vChannel * uFlickerScale + uSeed - vec2(uTime * uFlickerSpeed, 0.0)
      );
      water *= 1.0 + (flicker - 0.775) * uFlickerMix;

      // Каустика: оба слоя еле заметны и растянуты вдоль течения. Это не
      // главный рисунок дна, а слабый свет на песке.
      vec2 causticsPoint = vChannel + gradient * uCausticsShift;
      causticsPoint = vec2(causticsPoint.x, causticsPoint.y * uCausticsStretch);
      float lens = 1.0 + uCausticsLens * dot(gradient, vec2(0.7, 0.7));
      float causticsFine = waterCaustics(
        causticsPoint * uCausticsScale * lens - uCausticsFlow * uTime,
        uTime,
        uSeed.x,
        uCausticsLine,
        uCausticsDetail
      ) * uCausticsLod;
      float ca = cos(uCausticsAngle2);
      float sa = sin(uCausticsAngle2);
      vec2 coarsePoint = vec2(
        causticsPoint.x * ca - causticsPoint.y * sa,
        causticsPoint.x * sa + causticsPoint.y * ca
      );
      float causticsCoarse = waterCaustics(
        coarsePoint * uCausticsScale2 - uCausticsFlow2 * uTime,
        uTime,
        uSeed.y + 47.3,
        uCausticsLine2,
        0.0
      ) * uCausticsLod2;
      float caustics = causticsFine * uCausticsAlpha + causticsCoarse * uCausticsAlpha2;
      caustics *= 1.0 - 0.3 * tone;
      water += uCausticsColor * caustics * mix(0.6, 1.0, 1.0 - absorb);

      // Выплеск последним: за силуэтом нет ни гальки, ни каустики. Иначе
      // вода, растекаясь на берег, тащила бы за собой камни.
      float land = 1.0 - smoothstep(-uEdgeSoft, uEdgeSoft, shore);
      vec3 spill = uSpillColor * (0.92 + 0.16 * shadeValue);
      water = mix(water, spill, land * uSpillMix);

      // Мокрый песок лежит поверх берега, вода — поверх него. Обе части
      // премножены: Pixi смешивает premultiplied alpha.
      vec3 wetRgb = uWetColor * (wet * uWetAlpha);
      float wetAlpha = wet * uWetAlpha;
      outColor = vec4(
        water * waterMask + wetRgb * (1.0 - waterMask),
        waterMask + wetAlpha * (1.0 - waterMask)
      );
    `,
  },
};

/** Параметры тела реки: считаются при сборке сцены. */
export interface SceneRiverBodyOptions {
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
  /** Ширина мокрого песка за урезом в единицах сцены. */
  wetWidth: number;
  /** Сколько ячеек пятен мокроты на единицу сцены. */
  wetScale: number;
  /** Цвет мокрого песка. */
  wetColor: readonly [number, number, number];
  /** Плотность мокрого песка. */
  wetAlpha: number;
  /** Цвет выплеснувшейся воды за силуэтом. */
  spillColor: readonly [number, number, number];
  /** Доля цвета выплеска поверх дна. */
  spillMix: number;
  /** Текстура гальки. */
  bed: Texture;
  /** Тайл гальки в единицах сцены. */
  bedPeriod: number;
  /** Сдвиг выборки гальки в долях тайла. */
  bedShift: [number, number];
  /** Подмес к гальке. */
  bedTint: readonly [number, number, number];
  /** Песчаная подложка под водой. */
  sand: Texture;
  /** Тайл песка в единицах сцены. */
  sandPeriod: number;
  /** Сдвиг выборки песка в долях тайла. */
  sandShift: [number, number];
  /** Период пятен гальки на песке, единицы сцены. */
  pebbleScale: number;
  /** Базовая доля гальки на дне. */
  pebbleBase: number;
  /** Доля гальки в плотном пятне. */
  pebbleMix: number;
  /** Ширина перехода гальки в песок у уреза, единицы сцены. */
  bedFade: number;
  /** Цвет мелководья. */
  shallowColor: readonly [number, number, number];
  /** Доля цвета мелководья поверх дна. */
  shallowMix: number;
  /** Цвет толщи на глубине. */
  deepColor: readonly [number, number, number];
  /** Глубина полного поглощения в единицах сцены. */
  absorb: number;
  /** Ширина зоны, где преломление гаснет у уреза, единицы сцены. */
  shoal: number;
  /** Сдвиг дна нормалями в единицах сцены. */
  refraction: number;
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
  /** Модуляция цвета толщи высотой волны. */
  waveShade: number;
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
  /** Период и снос медленной модуляции цвета толщи. */
  shadeScale: number;
  shadeSpeed: number;
  /** Сколько ячеек маски широких пятен на единицу сцены. */
  flickerScale: number;
  /** Снос широких пятен, единиц шума в секунду. */
  flickerSpeed: number;
  /** Амплитуда широких пятен на теле. */
  flickerMix: number;
  /** Сдвиг узоров: разводит водоёмы между собой. */
  seed: [number, number];
  /** Сколько ячеек каустики на единицу сцены. */
  causticsScale: number;
  /** Толщина линии мелкого слоя в долях ячейки. */
  causticsLine: number;
  /** Яркость мелкого слоя. */
  causticsAlpha: number;
  /** 0…1: насколько мелкий слой виден на текущем зуме. */
  causticsLod: number;
  /** Цвет каустики. */
  causticsColor: readonly [number, number, number];
  /** Снос мелкого слоя вдоль русла, ячеек в секунду. */
  causticsFlow: [number, number];
  /** Сдвиг каустики наклоном волн в единицах сцены. */
  causticsShift: number;
  /** Растяжение каустики вдоль течения. */
  causticsStretch: number;
  /** Сколько ячеек крупного слоя каустики на единицу сцены. */
  causticsScale2: number;
  /** Толщина линии крупного слоя в долях ячейки. */
  causticsLine2: number;
  /** Яркость крупного слоя. */
  causticsAlpha2: number;
  /** 0…1: насколько крупный слой виден на текущем зуме. */
  causticsLod2: number;
  /** Снос крупного слоя вдоль русла, ячеек в секунду. */
  causticsFlow2: [number, number];
  /** Угол поворота крупного слоя, радианы. */
  causticsAngle2: number;
  /** Вес мелких нитей внутри ячеек мелкого слоя. */
  causticsDetail: number;
  /** Доля ячейки, на которую наклон меняет масштаб узора. */
  causticsLens: number;
  /** Потолок поглощения. */
  deepMix: number;
  /** Сколько ячеек «ям» на единицу сцены. */
  poolScale: number;
  /** Амплитуда «ям». */
  poolMix: number;
  /** Сколько ячеек мягких зон тона на единицу сцены. */
  toneScale: number;
  /** Скорость появления и исчезновения зон тона, ячеек в секунду. */
  toneSpeed: number;
  /** Амплитуда зон тона. */
  toneMix: number;
}

/** Шейдер тела реки. */
export class SceneRiverBodyShader {
  private readonly program = compileHighShaderGlProgram({
    name: 'water-river-body',
    bits: [localUniformBitGl, roundPixelsBitGl, BODY_BIT],
  });

  /** Тело реки: шейдер и обновление времени. */
  create(options: SceneRiverBodyOptions): { shader: Shader; update(seconds: number): void } {
    const uniforms = new UniformGroup({
      uFieldRect: { value: new Float32Array(options.fieldRect), type: 'vec4<f32>' },
      uDepthScale: { value: options.depthScale, type: 'f32' },
      uEdgeScale: { value: options.edgeScale, type: 'f32' },
      uEdgeSpeed: { value: options.edgeSpeed, type: 'f32' },
      uEdgeAmp: { value: options.edgeAmp, type: 'f32' },
      uEdgeFineAmp: { value: options.edgeFineAmp, type: 'f32' },
      uEdgeSoft: { value: options.edgeSoft, type: 'f32' },
      uWetWidth: { value: options.wetWidth, type: 'f32' },
      uWetScale: { value: options.wetScale, type: 'f32' },
      uWetColor: { value: new Float32Array(options.wetColor), type: 'vec3<f32>' },
      uWetAlpha: { value: options.wetAlpha, type: 'f32' },
      uSpillColor: { value: new Float32Array(options.spillColor), type: 'vec3<f32>' },
      uSpillMix: { value: options.spillMix, type: 'f32' },
      uBedPeriod: { value: options.bedPeriod, type: 'f32' },
      uBedShift: { value: new Float32Array(options.bedShift), type: 'vec2<f32>' },
      uBedTint: { value: new Float32Array(options.bedTint), type: 'vec3<f32>' },
      uSandPeriod: { value: options.sandPeriod, type: 'f32' },
      uSandShift: { value: new Float32Array(options.sandShift), type: 'vec2<f32>' },
      uPebbleScale: { value: options.pebbleScale, type: 'f32' },
      uPebbleBase: { value: options.pebbleBase, type: 'f32' },
      uPebbleMix: { value: options.pebbleMix, type: 'f32' },
      uBedFade: { value: options.bedFade, type: 'f32' },
      uShallowColor: { value: new Float32Array(options.shallowColor), type: 'vec3<f32>' },
      uShallowMix: { value: options.shallowMix, type: 'f32' },
      uDeepColor: { value: new Float32Array(options.deepColor), type: 'vec3<f32>' },
      uAbsorb: { value: options.absorb, type: 'f32' },
      uSeed: { value: new Float32Array(options.seed), type: 'vec2<f32>' },
      uTime: { value: 0, type: 'f32' },
      uFlowBank: { value: options.flowBank, type: 'f32' },
      uFlowCenter: { value: options.flowCenter, type: 'f32' },
      uWaveA: { value: new Float32Array(options.waveA), type: 'vec4<f32>' },
      uWaveB: { value: new Float32Array(options.waveB), type: 'vec4<f32>' },
      uWaveC: { value: new Float32Array(options.waveC), type: 'vec4<f32>' },
      uWaveEps: { value: options.waveEps, type: 'f32' },
      uWaveBump: { value: options.waveBump, type: 'f32' },
      uWaveShade: { value: options.waveShade, type: 'f32' },
      uWaveLod: { value: options.waveLod, type: 'f32' },
      uNoiseScaleA: { value: options.noiseScaleA, type: 'f32' },
      uNoiseSpeedA: { value: options.noiseSpeedA, type: 'f32' },
      uNoiseStretchA: { value: options.noiseStretchA, type: 'f32' },
      uNoiseWeightA: { value: options.noiseWeightA, type: 'f32' },
      uNoiseScaleB: { value: options.noiseScaleB, type: 'f32' },
      uNoiseSpeedB: { value: options.noiseSpeedB, type: 'f32' },
      uNoiseStretchB: { value: options.noiseStretchB, type: 'f32' },
      uNoiseWeightB: { value: options.noiseWeightB, type: 'f32' },
      uShadeScale: { value: options.shadeScale, type: 'f32' },
      uShadeSpeed: { value: options.shadeSpeed, type: 'f32' },
      uFlickerScale: { value: options.flickerScale, type: 'f32' },
      uFlickerSpeed: { value: options.flickerSpeed, type: 'f32' },
      uFlickerMix: { value: options.flickerMix, type: 'f32' },
      uShoal: { value: options.shoal, type: 'f32' },
      uRefraction: { value: options.refraction, type: 'f32' },
      uCausticsScale: { value: options.causticsScale, type: 'f32' },
      uCausticsLine: { value: options.causticsLine, type: 'f32' },
      uCausticsAlpha: { value: options.causticsAlpha, type: 'f32' },
      uCausticsLod: { value: options.causticsLod, type: 'f32' },
      uCausticsColor: { value: new Float32Array(options.causticsColor), type: 'vec3<f32>' },
      uCausticsFlow: { value: new Float32Array(options.causticsFlow), type: 'vec2<f32>' },
      uCausticsShift: { value: options.causticsShift, type: 'f32' },
      uCausticsStretch: { value: options.causticsStretch, type: 'f32' },
      uCausticsScale2: { value: options.causticsScale2, type: 'f32' },
      uCausticsLine2: { value: options.causticsLine2, type: 'f32' },
      uCausticsAlpha2: { value: options.causticsAlpha2, type: 'f32' },
      uCausticsLod2: { value: options.causticsLod2, type: 'f32' },
      uCausticsFlow2: { value: new Float32Array(options.causticsFlow2), type: 'vec2<f32>' },
      uCausticsAngle2: { value: options.causticsAngle2, type: 'f32' },
      uCausticsDetail: { value: options.causticsDetail, type: 'f32' },
      uCausticsLens: { value: options.causticsLens, type: 'f32' },
      uDeepMix: { value: options.deepMix, type: 'f32' },
      uPoolScale: { value: options.poolScale, type: 'f32' },
      uPoolMix: { value: options.poolMix, type: 'f32' },
      uToneScale: { value: options.toneScale, type: 'f32' },
      uToneSpeed: { value: options.toneSpeed, type: 'f32' },
      uToneMix: { value: options.toneMix, type: 'f32' },
    });
    const shader = new Shader({
      glProgram: this.program,
      resources: {
        bodyUniforms: uniforms,
        uField: options.field.source,
        uBed: options.bed.source,
        uSand: options.sand.source,
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
