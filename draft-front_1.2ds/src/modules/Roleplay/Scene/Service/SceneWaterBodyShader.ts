import { Shader, UniformGroup, compileHighShaderGlProgram, localUniformBitGl, roundPixelsBitGl } from 'pixi.js';
import type { Texture } from 'pixi.js';
import { WATER_CAUSTICS_GLSL, WATER_NOISE_GLSL } from '@/modules/Roleplay/Scene/Service/SceneWaterShaderSource';

/**
 * Бит тела воды.
 *
 * Тело лежит в слое террейна, под актёрами: это сама толща. Оно рисует дно —
 * гальку по мировым координатам, — приглушает её поглощением по глубине и
 * добавляет каустику на мели. Преломление дна считается здесь же: сдвиг
 * выборки берётся из тех же нормалей, что и блики поверхности.
 *
 * Урез тоже здесь: силуэт из поля глубины грубый, поэтому кромка гуляет
 * шумом, а за ней лежит мокрый песок. Иначе вода режется ровной линией, а
 * мокрота идёт двумя параллельными полосами.
 */
const BODY_BIT = {
  name: 'water-body-bit',
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
      uniform float uWashAmp;
      uniform float uWashRate;
      uniform float uWashFreq;

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
      uniform float uRefraction;

      uniform float uCausticsScale;
      uniform float uCausticsLine;
      uniform float uCausticsAlpha;
      uniform float uCausticsLod;
      uniform vec3 uCausticsColor;
      uniform vec2 uCausticsFlow;
      uniform float uCausticsShift;

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

      // Фаза наката общая с поверхностью: иначе вода и плёнка расходятся на
      // кромке, и выплеск не двигается вместе с водой.
      float phase = waterWashPhase(vChannel, uSeed, uTime, uWashRate, uWashFreq, uEdgeScale);
      float wash = waterWashValue(phase);
      float dry = waterDryness(phase);

      // Кромка не по бинарному силуэту: урез гуляет шумом. Это и неровный
      // берег, и выплеск воды за геометрию, и рваная кромка вместо кривой.
      float wobble = waterEdgeShift(vChannel, uSeed, uTime, uEdgeScale, uEdgeSpeed, uEdgeAmp, uEdgeFineAmp);
      float edgeDist = shore + wobble + uWashAmp * wash;
      float waterMask = smoothstep(-uEdgeSoft, uEdgeSoft, edgeDist);

      // Мокрый песок: постоянная влажная полоса у реки плюс сильная мокрая
      // полоса после наката, которая плавно сохнет до следующего.
      float wetPatch = waterFbm(vChannel * uWetScale + uSeed * 2.3);
      float wetBand = 1.0 - smoothstep(0.0, uWetWidth, -edgeDist);
      float wetAmount = wetBand * mix(0.35, 1.0, 1.0 - dry);
      float wet = wetAmount * (1.0 - waterMask);
      wet *= smoothstep(0.3, 0.6, wetPatch + 0.3);
      if (waterMask < 0.004 && wet < 0.004) discard;

      float depth = max(edgeDist, 0.0);
      // uLod гасит волны, когда их тайл становится мельче пикселя: иначе
      // в отдалении рябь превращается в звёздную пыль из бликов.
      vec2 slopeFine = waterSlope(vChannel, uSeed, uTime, uNoiseScale, uNoiseStretch, uNoiseSpeed) * uLod;
      vec2 slopeSwell = waterSlope(vChannel, uSeed + 31.7, uTime, uSwellScale, uNoiseStretch, uSwellSpeed) * uSwellLod;
      // Мелкая рябь двигает и дно: поверхность не читается ровной, даже когда
      // сама плёнка почти прозрачна.
      vec2 rippleSlope = waterSlope(vChannel, uSeed + 71.3, uTime, uRippleScale, 1.0, uRippleSpeed) * uRippleLod;
      vec2 slope = slopeFine + slopeSwell + rippleSlope;

      // У уреза преломление гаснет: иначе дно на кромке вылезает на берег.
      float shoal = smoothstep(0.0, uRefraction * 2.0, depth);
      vec2 refracted = vWorld + slope * uRefraction * shoal;

      // Песок продолжается под водой тем же тайлом, что и на берегу: на нём
      // пятнами рассыпана галька. К урезу галька гаснет в песок, поэтому
      // кромка воды больше не обрезает камни ножом.
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
      // Ямы и перекаты: вдоль русла поглощение гуляет, камни просвечивают
      // неравномерно. Шум статичный — ямы не ездят, поверх идёт каустика.
      float pool = waterFbm(vec2(vChannel.x * uPoolScale, vChannel.y * uPoolScale * 0.5) + uSeed);
      absorb = min(absorb * mix(1.0 - uPoolMix, 1.0 + uPoolMix, pool), uDeepMix);
      // Мелководье подкрашено водой: чистая галька читается сушей.
      vec3 shallow = mix(bed, uShallowColor, uShallowMix);
      vec3 color = mix(shallow, uDeepColor, absorb);
      // Мелкая рябь чуть меняет оттенок: поверхность читается живой, но без
      // белых бликов и пятен. Это именно цвет, а не свет.
      float rippleValue = waterFbm(
        vec2(vChannel.x, vChannel.y) * uRippleScale * 1.6 - vec2(uTime * uRippleSpeed * 0.8, 0.0) + uSeed * 5.3
      );
      vec3 water = color;
      water *= 1.0 + (rippleValue - 0.45) * 0.16;

      // Мягкие зоны тона: не плывут по реке, а появляются и исчезают на месте.
      // Это не пятна света, а чуть другой оттенок толщи.
      vec2 tonePoint = vec2(vChannel.x, vChannel.y * 0.8) * uToneScale + uSeed * 4.3;
      float toneA = waterFbm(tonePoint + vec2(uTime * uToneSpeed, uTime * uToneSpeed * 0.7));
      float toneB = waterFbm(tonePoint * 1.7 + vec2(7.1, 3.3) - vec2(uTime * uToneSpeed * 0.6, 0.0));
      float tone = smoothstep(0.3, 0.65, toneA * 0.6 + toneB * 0.4);
      water *= 1.0 + (tone - 0.5) * uToneMix;

      // Две сетки каустики. Наклон воды сдвигает и растягивает обе — это
      // преломление; крупная идёт под своим углом и с другой скоростью,
      // иначе две одинаковые сетки читаются одной.
      // Каустика идёт за медленным наклоном: мелкая рябь на ней только чуть
      // дрожит, иначе сетка дёргается.
      vec2 causticsSlope = slopeSwell + slopeFine * 0.5 + rippleSlope * 0.3;
      vec2 causticsPoint = vChannel + causticsSlope * uCausticsShift;
      float lens = 1.0 + uCausticsLens * dot(causticsSlope, vec2(0.7, 0.7));
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
      // Каустика держится и в глубине: на дне реки она читается, а не только
      // на мели, иначе вода превращается в ровную заливку. В мягких зонах
      // тона она гаснет: взвесь не пропускает свет.
      float caustics = causticsFine * uCausticsAlpha + causticsCoarse * uCausticsAlpha2;
      caustics *= 1.0 - 0.3 * tone;
      water += uCausticsColor * caustics * mix(0.6, 1.0, 1.0 - absorb);

      // Выплеск последним: за силуэтом нет ни гальки, ни каустики. Иначе
      // вода, растекаясь на берег, тащила бы за собой камни.
      float land = 1.0 - smoothstep(-uEdgeSoft, uEdgeSoft, shore);
      vec3 spill = uSpillColor * (0.9 + 0.2 * rippleValue);
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

/** Параметры тела воды: считаются при сборке сцены, меняются только со временем. */
export interface SceneWaterBodyOptions {
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
  /** Подмес к гальке: дно под водой не должно читаться сухим. */
  bedTint: readonly [number, number, number];
  /** Песчаная подложка под водой: тот же тайл, что у берега. */
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
  /** Доля цвета мелководья поверх гальки. */
  shallowMix: number;
  /** Цвет толщи на глубине. */
  deepColor: readonly [number, number, number];
  /** Глубина полного поглощения в единицах сцены. */
  absorb: number;
  /** Смещение дна нормалями в единицах сцены. */
  refraction: number;
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
  /** Сдвиг узоров: разводит водоёмы между собой. */
  seed: [number, number];
  /** Сколько ячеек каустики на единицу сцены. */
  causticsScale: number;
  /** Толщина линии каустики в долях ячейки. */
  causticsLine: number;
  /** Яркость каустики. */
  causticsAlpha: number;
  /** 0…1: насколько каустика видна на текущем зуме. */
  causticsLod: number;
  /** Цвет каустики. */
  causticsColor: readonly [number, number, number];
  /** Снос каустики вдоль русла, ячеек в секунду. */
  causticsFlow: [number, number];
  /** Сдвиг каустики наклоном волн в единицах сцены. */
  causticsShift: number;
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
  /** Вес мелких нитей внутри ячеек мелкого слоя: 0 — только сетка. */
  causticsDetail: number;
  /** Доля ячейки, на которую наклон меняет масштаб узора. */
  causticsLens: number;
  /** Потолок поглощения: в глубине остаётся просвет дна. */
  deepMix: number;
  /** Сколько ячеек «ям» на единицу сцены. */
  poolScale: number;
  /** Амплитуда «ям»: доля, на которую гуляет поглощение. */
  poolMix: number;
  /** Сколько ячеек мягких зон тона на единицу сцены. */
  toneScale: number;
  /** Скорость появления и исчезновения зон тона, ячеек в секунду. */
  toneSpeed: number;
  /** Амплитуда зон тона: доля, на которую гуляет оттенок. */
  toneMix: number;
}

/**
 * Шейдер тела воды.
 *
 * Одна программа на все реки, свои юниформы — у каждого водоёма.
 */
export class SceneWaterBodyShader {
  private readonly program = compileHighShaderGlProgram({
    name: 'water-body',
    bits: [localUniformBitGl, roundPixelsBitGl, BODY_BIT],
  });

  /** Тело водоёма: шейдер и обновление времени. */
  create(options: SceneWaterBodyOptions): { shader: Shader; update(seconds: number): void } {
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
      uRefraction: { value: options.refraction, type: 'f32' },
      uCausticsScale: { value: options.causticsScale, type: 'f32' },
      uCausticsLine: { value: options.causticsLine, type: 'f32' },
      uCausticsAlpha: { value: options.causticsAlpha, type: 'f32' },
      uCausticsLod: { value: options.causticsLod, type: 'f32' },
      uCausticsColor: { value: new Float32Array(options.causticsColor), type: 'vec3<f32>' },
      uCausticsFlow: { value: new Float32Array(options.causticsFlow), type: 'vec2<f32>' },
      uCausticsShift: { value: options.causticsShift, type: 'f32' },
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
      // Pixi привязывает сэмплер только для TextureSource: Texture — обёртка
      // с кадром, её GL-синхронизация пропускает и сэмплер остаётся пустым.
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
