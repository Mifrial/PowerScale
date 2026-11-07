import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';

/** Развёрнутая лента: точки, ширины, единичные касательные и номер сегмента у каждого сэмпла. */
export interface RibbonFlat {
  points: MapPoint[];
  widths: number[];
  /** Единичная касательная в каждом сэмпле: непрерывна внутри кривой Безье. */
  tangents: MapPoint[];
  segmentIndices: number[];
  /** Накопленная длина по дуге, начиная с 0. */
  lengths: number[];
  totalLength: number;
}

/** Края ленты: левая и правая границы. */
export interface RibbonEdges {
  left: MapPoint[];
  right: MapPoint[];
}

/** Участок одинакового цвета; границы — дробные индексы сэмплов. */
export interface RibbonRun {
  from: number;
  to: number;
  color: string;
}

/** Полоса градиентного перехода между двумя цветами. */
export interface RibbonTransition {
  from: number;
  to: number;
  fromColor: string;
  toColor: string;
}

/** Готовая раскладка ленты для рисования. */
export interface RibbonLayout {
  flat: RibbonFlat;
  edges: RibbonEdges;
  runs: RibbonRun[];
  transitions: RibbonTransition[];
}

/** Частица ряби: постоянные свойства блика, не зависящие от времени. */
export interface FlowParticle {
  /** Начальное положение вдоль дуги, доля 0..1. */
  phase: number;
  /** Поперечное смещение, доля полуширины -1..1. */
  lateral: number;
  /** Длина вдоль осевой, px. */
  length: number;
  /** Толщина в середине, px. */
  thickness: number;
  /** Готовый цвет блика. */
  color: string;
  /** Пиковая непрозрачность, 0..1. */
  alpha: number;
  /** Частота мерцания, Гц. */
  pulse: number;
  /** Фаза мерцания, доля 0..1. */
  pulsePhase: number;
  /** Множитель скорости: центр русла течёт быстрее берегов. */
  speedScale: number;
}

/** Блик ряби на текущий кадр: изогнутая полоса и её бледный ореол. */
export interface FlowGlint {
  /** Основная полоса: одна кромка вперёд, вторая назад. */
  points: MapPoint[];
  /** Широкий бледный ореол вокруг основной полосы. */
  halo: MapPoint[];
  alpha: number;
  color: string;
}

/** Геометрия ленты: непрерывные участки цвета, градиенты и рябь течения. */
export class MapRibbonService {
  private readonly flowCache = new Map<string, FlowParticle[]>();
  private readonly layoutCache = new Map<string, RibbonLayout>();

  constructor(private readonly geometry: MapGeometryService) {}

  /**
   * Разворачивает ленту, режет её на участки цвета и считает зоны переходов.
   *
   * Раскладка кэшируется по подписи геометрии: анимация ряби не пересчитывает
   * разворот и края каждый кадр.
   */
  layout(line: MapLine, samples = 16): RibbonLayout {
    const key = this.layoutSignature(line, samples);
    const cached = this.layoutCache.get(key);
    if (cached) return cached;

    const flat = this.flatten(line, samples);
    const runs = this.colorRuns(flat, line);
    const transitions: RibbonTransition[] = [];
    const edges = this.ribbonEdges(flat.points, flat.widths);
    const result: RibbonLayout = {
      flat,
      edges,
      runs: this.cutTransitions(runs, flat, line.colorBlend ?? 16, transitions),
      transitions,
    };

    this.layoutCache.set(key, result);
    if (this.layoutCache.size > 64) {
      const oldest = this.layoutCache.keys().next().value;
      if (oldest !== undefined) this.layoutCache.delete(oldest);
    }

    return result;
  }

  /** Разворачивает осевую линию в сэмплы с ширинами и номером сегмента. */
  flatten(line: MapLine, samples = 16): RibbonFlat {
    const points = line.points;
    const widths = line.widths;
    const handles = line.handles ?? [];
    if (points.length < 2 || widths.length !== points.length) {
      return {
        points: points.map((point) => [point[0], point[1]] as MapPoint),
        widths: points.map((_, index) => Math.max(1, widths[index] ?? 1)),
        tangents: points.map(() => [1, 0] as MapPoint),
        segmentIndices: points.map((_, index) => Math.max(0, index - 1)),
        lengths: points.map(() => 0),
        totalLength: 0,
      };
    }

    const steps = Math.max(1, samples);
    const flatPoints: MapPoint[] = [];
    const flatWidths: number[] = [];
    const flatTangents: MapPoint[] = [];
    const segmentIndices: number[] = [];
    for (let index = 1; index < points.length; index += 1) {
      const start = points[index - 1];
      const end = points[index];
      const startOut = handles[index - 1]?.out ?? [0, 0];
      const endIn = handles[index]?.in ?? [0, 0];
      const controlOne: MapPoint = [start[0] + startOut[0], start[1] + startOut[1]];
      const controlTwo: MapPoint = [end[0] + endIn[0], end[1] + endIn[1]];
      const startWidth = Math.max(1, widths[index - 1]);
      const endWidth = Math.max(1, widths[index]);
      for (let step = index === 1 ? 0 : 1; step <= steps; step += 1) {
        const t = step / steps;
        flatPoints.push(this.geometry.cubicPoint(start, controlOne, controlTwo, end, t));
        flatWidths.push(startWidth + (endWidth - startWidth) * t);
        flatTangents.push(this.cubicTangent(start, controlOne, controlTwo, end, t));
        segmentIndices.push(index - 1);
      }
    }

    return this.withLengths(this.dedupe(flatPoints, flatWidths, flatTangents, segmentIndices));
  }

  /** Единичная касательная кубической кривой Безье. */
  private cubicTangent(
    start: MapPoint,
    controlOne: MapPoint,
    controlTwo: MapPoint,
    end: MapPoint,
    t: number,
  ): MapPoint {
    const inverse = 1 - t;
    const a = 3 * inverse * inverse;
    const b = 6 * inverse * t;
    const c = 3 * t * t;
    const x = a * (controlOne[0] - start[0]) + b * (controlTwo[0] - controlOne[0]) + c * (end[0] - controlTwo[0]);
    const y = a * (controlOne[1] - start[1]) + b * (controlTwo[1] - controlOne[1]) + c * (end[1] - controlTwo[1]);
    const length = Math.hypot(x, y);
    if (length < 1e-9) {
      const fallbackX = end[0] - start[0];
      const fallbackY = end[1] - start[1];
      const fallbackLength = Math.hypot(fallbackX, fallbackY);
      if (fallbackLength < 1e-9) return [1, 0];

      return [fallbackX / fallbackLength, fallbackY / fallbackLength];
    }

    return [x / length, y / length];
  }

  /** Максимальные участки одинакового эффективного цвета. */
  colorRuns(flat: RibbonFlat, line: MapLine): RibbonRun[] {
    if (flat.points.length === 0) return [];

    const runs: RibbonRun[] = [];
    let start = 0;
    let color = this.segmentColor(line, flat.segmentIndices[0] ?? 0);
    for (let index = 1; index < flat.points.length; index += 1) {
      const nextColor = this.segmentColor(line, flat.segmentIndices[index] ?? 0);
      if (nextColor === color) continue;

      runs.push({ from: start, to: index, color });
      start = index;
      color = nextColor;
    }
    runs.push({ from: start, to: flat.points.length - 1, color });

    return runs;
  }

  /** Края ленты с ограничением митры: острые углы не дают игл и петель. */
  ribbonEdges(points: MapPoint[], widths: number[]): RibbonEdges {
    const left: MapPoint[] = [];
    const right: MapPoint[] = [];
    const last = points.length - 1;
    points.forEach((point, index) => {
      const halfWidth = Math.max(1, widths[index] ?? 1) / 2;
      let offset: MapPoint;
      if (index === 0) {
        offset = this.segmentNormal(point, points[1] ?? point);
      } else if (index === last) {
        offset = this.segmentNormal(points[index - 1], point);
      } else {
        const before = this.segmentNormal(points[index - 1], point);
        const after = this.segmentNormal(point, points[index + 1]);
        const sum: MapPoint = [before[0] + after[0], before[1] + after[1]];
        const length = Math.hypot(sum[0], sum[1]);
        if (length < 1e-6) {
          offset = after;
        } else {
          const miter: MapPoint = [sum[0] / length, sum[1] / length];
          const dot = miter[0] * after[0] + miter[1] * after[1];
          const scale = dot > 0.2 ? Math.min(2.5, 1 / dot) : 1;
          offset = [miter[0] * scale, miter[1] * scale];
        }
      }
      left.push([point[0] + offset[0] * halfWidth, point[1] + offset[1] * halfWidth]);
      right.push([point[0] - offset[0] * halfWidth, point[1] - offset[1] * halfWidth]);
    });

    return { left, right };
  }

  /**
   * Полоса между двумя долями ширины: -1 — левый берег, 1 — правый.
   */
  ribbonBand(flat: RibbonFlat, fromRatio: number, toRatio: number): MapPoint[] {
    if (flat.points.length < 2) return [];

    const first: MapPoint[] = [];
    const second: MapPoint[] = [];
    flat.points.forEach((point, index) => {
      const tangent = flat.tangents[index] ?? [1, 0];
      const normal: MapPoint = [-tangent[1], tangent[0]];
      const halfWidth = Math.max(1, flat.widths[index] ?? 1) / 2;
      first.push([point[0] + normal[0] * fromRatio * halfWidth, point[1] + normal[1] * fromRatio * halfWidth]);
      second.push([point[0] + normal[0] * toRatio * halfWidth, point[1] + normal[1] * toRatio * halfWidth]);
    });

    return [...first, ...second.reverse()];
  }

  /** Затемняет цвет на долю `ratio`, 0–1. */
  shade(color: string, ratio: number): string {
    return this.mixHex(color, '#000000', ratio);
  }

  /** Точка края на дробном индексе сэмпла. */
  edgePoint(edge: MapPoint[], position: number): MapPoint {
    const index = Math.floor(position);
    const next = Math.min(edge.length - 1, index + 1);
    const t = Math.max(0, Math.min(1, position - index));
    const start = edge[Math.max(0, index)];

    return [start[0] + (edge[next][0] - start[0]) * t, start[1] + (edge[next][1] - start[1]) * t];
  }

  /** Точка осевой на дробном индексе сэмпла. */
  centerPoint(flat: RibbonFlat, position: number): MapPoint {
    return this.edgePoint(flat.points, position);
  }

  /**
   * Постоянные частицы ряби: раскладка, цвет и цикл мерцания.
   *
   * Раскладка детерминирована `seed` и кэшируется по подписи ленты.
   */
  flowParticles(line: MapLine, layout: RibbonLayout): FlowParticle[] {
    const flow = line.decor?.flow;
    const flat = layout.flat;
    if (!flow?.enabled || flat.totalLength < 8 || flat.points.length < 2) return [];

    const key = this.flowSignature(line, flat);
    const cached = this.flowCache.get(key);
    if (cached) return cached;

    const random = this.seededRandom(flow.seed || line.id || 1);
    const density = Math.max(0, Math.min(100, flow.density));
    const opacity = Math.max(0, Math.min(1, flow.opacity / 100));
    const spacing = Math.max(8, 24 - density * 0.16);
    const count = Math.max(2, Math.min(320, Math.ceil(flat.totalLength / spacing)));
    const densityScale = 1.15 - density * 0.0045;
    const particles: FlowParticle[] = [];

    for (let index = 0; index < count; index += 1) {
      const phase = random();
      const lateral = (random() < 0.5 ? -1 : 1) * (0.18 + random() * 0.66);
      const position = this.positionAtDistance(flat, phase * flat.totalLength);
      const water = this.waterColorAt(line, flat, position);
      const tint = this.mixHex(water, flow.color, 0.3 + random() * 0.55);
      const localWidth = this.interpolateValue(flat.widths, position);
      const localScale = Math.max(0.55, Math.min(1.6, localWidth / 24));
      particles.push({
        phase,
        lateral,
        length: (16 + random() * 48) * localScale * densityScale,
        thickness: Math.max(0.7, (0.7 + random() * 2.3) * Math.min(1.4, localScale) * densityScale),
        color: this.mixHex(tint, '#ffffff', 0.05 + random() * 0.18),
        alpha: opacity * (0.35 + random() * 0.65) * (1 - density * 0.003),
        pulse: 0.1 + random() * 0.22,
        pulsePhase: random(),
        speedScale: (0.72 + (1 - Math.abs(lateral)) * 0.5) * (0.85 + random() * 0.3),
      });
    }

    this.flowCache.set(key, particles);
    if (this.flowCache.size > 64) {
      const oldest = this.flowCache.keys().next().value;
      if (oldest !== undefined) this.flowCache.delete(oldest);
    }

    return particles;
  }

  /**
   * Блики на момент времени: изогнутые полосы вдоль русла и текущая непрозрачность.
   *
   * Каждая частица едет по течению со своей скоростью, независимо мерцает и
   * гаснет у берегов и на концах русла.
   */
  flowGlints(
    particles: FlowParticle[],
    flat: RibbonFlat,
    timeMs: number,
    speed: number,
    direction: number,
    reducedMotion = false,
  ): FlowGlint[] {
    if (particles.length === 0 || flat.totalLength < 1) return [];

    const elapsed = reducedMotion ? 0 : timeMs / 1000;
    const fadeZone = Math.min(0.08, 60 / flat.totalLength);
    const glints: FlowGlint[] = [];

    for (const particle of particles) {
      const travel = (elapsed * speed * particle.speedScale * direction) / flat.totalLength;
      const progress = (((particle.phase + travel) % 1) + 1) % 1;
      const pulse = 0.5 - 0.5 * Math.cos(Math.PI * 2 * (particle.pulsePhase + elapsed * particle.pulse));
      const endFade = Math.min(this.smoothStep(0, fadeZone, progress), 1 - this.smoothStep(1 - fadeZone, 1, progress));
      const distance = progress * flat.totalLength;
      const alpha = particle.alpha * pulse * endFade * this.bankFade(flat, distance, particle);
      if (alpha <= 0.004) continue;

      const band = this.glintBand(flat, distance, particle);
      if (!band) continue;
      glints.push({ points: band.core, halo: band.halo, alpha, color: particle.color });
    }

    return glints;
  }

  /** Изогнутая полоса блика: кромки идут по дуге, толщина тает к концам. */
  private glintBand(
    flat: RibbonFlat,
    distance: number,
    particle: FlowParticle,
  ): { core: MapPoint[]; halo: MapPoint[] } | null {
    const steps = 6;
    const halfLength = particle.length / 2;
    const coreInner: MapPoint[] = [];
    const coreOuter: MapPoint[] = [];
    const haloInner: MapPoint[] = [];
    const haloOuter: MapPoint[] = [];
    const shiftRatio = Math.max(-1, Math.min(1, particle.lateral)) * 0.9;

    for (let step = 0; step <= steps; step += 1) {
      const t = step / steps;
      const clamped = Math.max(0, Math.min(flat.totalLength, distance - halfLength + particle.length * t));
      const position = this.positionAtDistance(flat, clamped);
      const center = this.centerPoint(flat, position);
      const tangent = this.tangentAt(flat, position);
      const normal: MapPoint = [-tangent[1], tangent[0]];
      const halfWidth = this.interpolateValue(flat.widths, position) / 2;
      if (halfWidth < 1) return null;

      const taper = Math.sin(Math.PI * t);
      const maxHalf = Math.max(0.2, halfWidth * 0.92);
      let coreHalf = (particle.thickness * taper) / 2;
      let haloHalf = coreHalf * 2.2 + 0.35 * taper;
      if (haloHalf > maxHalf) {
        const scale = maxHalf / haloHalf;
        haloHalf = maxHalf;
        coreHalf = Math.min(coreHalf * scale, maxHalf * 0.7);
      }
      const limit = Math.max(0, halfWidth - haloHalf - 0.2);
      const shift = Math.max(-limit, Math.min(limit, shiftRatio * halfWidth));
      this.pushBandPoint(coreInner, coreOuter, center, normal, shift, coreHalf);
      this.pushBandPoint(haloInner, haloOuter, center, normal, shift, haloHalf);
    }

    return {
      core: [...coreInner, ...[...coreOuter].reverse()],
      halo: [...haloInner, ...[...haloOuter].reverse()],
    };
  }

  /** Пара кромок полосы на одном сэмпле. */
  private pushBandPoint(
    inner: MapPoint[],
    outer: MapPoint[],
    center: MapPoint,
    normal: MapPoint,
    shift: number,
    half: number,
  ): void {
    inner.push([center[0] + normal[0] * (shift + half), center[1] + normal[1] * (shift + half)]);
    outer.push([center[0] + normal[0] * (shift - half), center[1] + normal[1] * (shift - half)]);
  }

  /** Мягкое затухание блика у берега. */
  private bankFade(flat: RibbonFlat, distance: number, particle: FlowParticle): number {
    const position = this.positionAtDistance(flat, distance);
    const halfWidth = this.interpolateValue(flat.widths, position) / 2;
    const room = halfWidth - Math.abs(particle.lateral) * halfWidth * 0.9 - particle.thickness * 1.1;

    return this.smoothStep(0, Math.max(1.5, halfWidth * 0.2), room);
  }

  /** Плавный переход 0..1 между двумя границами. */
  private smoothStep(edge0: number, edge1: number, value: number): number {
    if (edge1 <= edge0) return value < edge0 ? 0 : 1;
    const t = Math.max(0, Math.min(1, (value - edge0) / (edge1 - edge0)));

    return t * t * (3 - 2 * t);
  }

  /** Цвет воды под точкой развёрнутой ленты. */
  private waterColorAt(line: MapLine, flat: RibbonFlat, position: number): string {
    const index = Math.max(0, Math.min(flat.segmentIndices.length - 1, Math.round(position)));

    return this.segmentColor(line, flat.segmentIndices[index] ?? 0);
  }

  /** Подпись ленты для кэша частиц: геометрия и параметры течения. */
  private flowSignature(line: MapLine, flat: RibbonFlat): string {
    let widthSum = 0;
    for (const width of flat.widths) widthSum += width;
    const colors = line.segments.map((segment) => segment.color ?? '').join(',');

    return [
      line.id,
      line.color,
      flat.totalLength.toFixed(1),
      flat.widths.length,
      widthSum.toFixed(1),
      colors,
      JSON.stringify(line.decor ?? {}),
    ].join('|');
  }

  /** Смешивает два цвета `#rrggbb`; ratio 0 — from, 1 — to. */
  private mixHex(from: string, to: string, ratio: number): string {
    const start = this.parseHex(from) ?? [0, 0, 0];
    const end = this.parseHex(to) ?? [255, 255, 255];
    const t = Math.max(0, Math.min(1, ratio));
    const channels = start.map((channel, index) =>
      Math.round(channel + (end[index] - channel) * t)
        .toString(16)
        .padStart(2, '0'),
    );

    return `#${channels.join('')}`;
  }

  /** Разбирает `#rrggbb`. */
  private parseHex(color: string): [number, number, number] | null {
    const match = /^#([0-9a-fA-F]{6})$/.exec(color.trim());
    if (!match) return null;
    const value = Number.parseInt(match[1], 16);

    return [(value >> 16) & 255, (value >> 8) & 255, value & 255];
  }

  /** Эффективный цвет сегмента: свой или цвет ленты. */
  segmentColor(line: MapLine, segmentIndex: number): string {
    return line.segments[segmentIndex]?.color ?? line.color;
  }

  /** Дробный индекс сэмпла на расстоянии по дуге. */
  positionAtDistance(flat: RibbonFlat, distance: number): number {
    const lengths = flat.lengths;
    if (lengths.length < 2) return 0;
    const target = Math.max(0, Math.min(flat.totalLength, distance));
    let low = 0;
    let high = lengths.length - 1;
    while (low < high - 1) {
      const middle = (low + high) >> 1;
      if (lengths[middle] <= target) {
        low = middle;
      } else {
        high = middle;
      }
    }
    const span = lengths[high] - lengths[low];
    const t = span < 1e-9 ? 0 : (target - lengths[low]) / span;

    return low + t;
  }

  private cutTransitions(
    runs: RibbonRun[],
    flat: RibbonFlat,
    colorBlend: number,
    transitions: RibbonTransition[],
  ): RibbonRun[] {
    const blend = Math.max(0, colorBlend);
    if (blend === 0 || runs.length < 2 || flat.totalLength <= 0) {
      return runs;
    }

    const boundaries = runs.slice(0, -1).map((_, index) => {
      const next = runs[index + 1].from;

      return flat.lengths[Math.min(next, flat.lengths.length - 1)];
    });
    const result = runs.map((run) => ({ ...run }));
    const half = blend / 2;
    for (let index = 0; index < boundaries.length; index += 1) {
      const boundary = boundaries[index];
      const previous = index === 0 ? 0 : boundaries[index - 1];
      const next = index === boundaries.length - 1 ? flat.totalLength : boundaries[index + 1];
      const left = Math.max(boundary - half, (previous + boundary) / 2);
      const right = Math.min(boundary + half, (boundary + next) / 2);
      if (right - left < 0.25) continue;

      const from = this.positionAtDistance(flat, left);
      const to = this.positionAtDistance(flat, right);
      result[index].to = from;
      result[index + 1].from = to;
      transitions.push({
        from,
        to,
        fromColor: result[index].color,
        toColor: result[index + 1].color,
      });
    }

    return result;
  }

  private flattenPointDistance(points: MapPoint[]): { lengths: number[]; total: number } {
    const lengths = [0];
    for (let index = 1; index < points.length; index += 1) {
      lengths.push(lengths[index - 1] + this.geometry.distance(points[index - 1], points[index]));
    }

    return { lengths, total: lengths[lengths.length - 1] ?? 0 };
  }

  private withLengths(flat: Omit<RibbonFlat, 'lengths' | 'totalLength'>): RibbonFlat {
    const { lengths, total } = this.flattenPointDistance(flat.points);

    return { ...flat, lengths, totalLength: total };
  }

  private dedupe(
    points: MapPoint[],
    widths: number[],
    tangents: MapPoint[],
    segmentIndices: number[],
  ): Omit<RibbonFlat, 'lengths' | 'totalLength'> {
    const keptPoints: MapPoint[] = [];
    const keptWidths: number[] = [];
    const keptTangents: MapPoint[] = [];
    const keptSegments: number[] = [];
    points.forEach((point, index) => {
      const previous = keptPoints[keptPoints.length - 1];
      if (previous && Math.hypot(point[0] - previous[0], point[1] - previous[1]) < 1e-6) return;
      keptPoints.push([point[0], point[1]]);
      keptWidths.push(widths[index]);
      keptTangents.push(tangents[index]);
      keptSegments.push(segmentIndices[index]);
    });

    return {
      points: keptPoints,
      widths: keptWidths,
      tangents: keptTangents,
      segmentIndices: keptSegments,
    };
  }

  /** Подпись геометрии для кэша раскладки. */
  private layoutSignature(line: MapLine, samples: number): string {
    let hash = 2166136261;
    const mix = (value: number): void => {
      hash ^= Math.round(value * 4) & 0xffffffff;
      hash = Math.imul(hash, 16777619);
    };
    mix(line.points.length);
    mix(samples);
    for (const point of line.points) {
      mix(point[0]);
      mix(point[1]);
    }
    for (const width of line.widths) mix(width);
    for (const handle of line.handles ?? []) {
      mix(handle.in[0]);
      mix(handle.in[1]);
      mix(handle.out[0]);
      mix(handle.out[1]);
    }
    for (const segment of line.segments) {
      mix(segment.speed);
      mix(segment.color === null ? 1 : 0);
    }

    return [
      line.id,
      hash >>> 0,
      line.color,
      line.colorBlend ?? 16,
      line.segments.map((segment) => segment.color ?? '').join(','),
    ].join('|');
  }

  private interpolateValue(values: number[], position: number): number {
    const index = Math.floor(position);
    const next = Math.min(values.length - 1, index + 1);
    const t = Math.max(0, Math.min(1, position - index));

    return values[Math.max(0, index)] + (values[next] - values[Math.max(0, index)]) * t;
  }

  /** Касательная на дробном индексе сэмпла: интерполяция единичных касательных. */
  private tangentAt(flat: RibbonFlat, position: number): MapPoint {
    const last = flat.tangents.length - 1;
    if (last < 0) return [1, 0];
    const index = Math.max(0, Math.min(last, Math.floor(position)));
    const next = Math.min(last, index + 1);
    const t = Math.max(0, Math.min(1, position - index));
    const start = flat.tangents[index];
    const end = flat.tangents[next];
    const x = start[0] + (end[0] - start[0]) * t;
    const y = start[1] + (end[1] - start[1]) * t;
    const length = Math.hypot(x, y);
    if (length < 1e-9) return start;

    return [x / length, y / length];
  }

  private segmentNormal(start: MapPoint, end: MapPoint): MapPoint {
    const deltaX = end[0] - start[0];
    const deltaY = end[1] - start[1];
    const length = Math.hypot(deltaX, deltaY);
    if (length === 0) return [0, 0];

    return [-deltaY / length, deltaX / length];
  }

  private seededRandom(seed: number): () => number {
    let state = seed >>> 0;

    return () => {
      state = (state + 0x6d2b79f5) >>> 0;
      let value = state;
      value = Math.imul(value ^ (value >>> 15), value | 1);
      value ^= value + Math.imul(value ^ (value >>> 7), value | 61);

      return ((value ^ (value >>> 14)) >>> 0) / 4294967296;
    };
  }
}
