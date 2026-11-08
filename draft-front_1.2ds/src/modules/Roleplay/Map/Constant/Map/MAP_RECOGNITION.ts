/** Настройки и границы распознавания объектов по подложке. */
export const MAP_RECOGNITION = {
  defaults: {
    tolerance: 12,
    ignoreLuminance: false,
    minArea: 40,
    maxArea: 0,
    gap: 1,
    simplify: 1,
    maxVertices: 24,
    smoothArcs: true,
  },
  limits: {
    tolerance: { min: 0, max: 100 },
    minArea: { min: 4, max: 200000 },
    gap: { min: 0, max: 4 },
    simplify: { min: 0, max: 6 },
    maxVertices: { min: 8, max: 48 },
    maxSamples: 8,
    maxImageSide: 2560,
  },
  preview: {
    maskOpacity: 0.35,
    maskColor: '#e53935',
    stroke: 'rgba(229, 57, 53, 0.95)',
    strokeWidth: 1.5,
    excludedStroke: 'rgba(120, 120, 120, 0.75)',
    excludedFill: 'rgba(120, 120, 120, 0.22)',
    dash: 3,
    /** Радиус попадания по контуру, экранные px: клик по линии тоже исключает. */
    hitRadius: 8,
  },
  color: {
    /** Во сколько единиц допуска превращается один шаг канала 0–255. */
    channelPerTolerance: 2.55,
    /** Допуск сравнения хроматичности при отключённой яркости. */
    chromaPerTolerance: 0.0025,
    /** Доля яркости образца, ниже которой пиксель считается тенью/контуром. */
    minLuminanceShare: 0.35,
    /** Образец темнее этого уровня сравнивается только по RGB. */
    darkSampleLevel: 60,
  },
  simplify: {
    /** Во сколько раз растёт допуск, если вершин всё ещё слишком много. */
    growth: 1.5,
    /** Предел роста допуска, px. */
    maxTolerance: 128,
  },
  arc: {
    /** Поворот круче этого — угол здания, а не точка дуги. */
    cornerDegrees: 35,
    /** Допустимое отклонение вершин от окружности, px. */
    deviation: 1.5,
    /** Допустимый отход сглаженного сегмента от сырого контура, px. */
    contourDeviation: 2.5,
    /** Дуги меньше этого радиуса не аппроксимируются, px. */
    minRadius: 5,
    /** Минимальный разворот участка, чтобы считать его дугой, рад. */
    minSweep: 0.6,
    /** Потолок разворота одного безье-сегмента: 90°. */
    maxSegment: Math.PI / 2,
  },
} as const;
