import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';

/** Развёрнутая лента: точки, ширины и номер сегмента у каждого сэмпла. */
export interface RibbonFlat {
  points: MapPoint[];
  widths: number[];
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

/** Блик течения: короткий отрезок вдоль осевой. */
export interface FlowMark {
  start: MapPoint;
  end: MapPoint;
  width: number;
  alpha: number;
}

/** Геометрия ленты: непрерывные участки цвета, градиенты и блики течения. */
export class MapRibbonService {
  constructor(private readonly geometry: MapGeometryService) {}

  /**
   * Разворачивает ленту, режет её на участки цвета и считает зоны переходов.
   */
  layout(line: MapLine, samples = 16): RibbonLayout {
    const flat = this.flatten(line, samples);
    const runs = this.colorRuns(flat, line);
    const transitions: RibbonTransition[] = [];
    const edges = this.ribbonEdges(flat.points, flat.widths);

    return {
      flat,
      edges,
      runs: this.cutTransitions(runs, flat, line.colorBlend ?? 16, transitions),
      transitions,
    };
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
        segmentIndices: points.map((_, index) => Math.max(0, index - 1)),
        lengths: points.map(() => 0),
        totalLength: 0,
      };
    }

    const steps = Math.max(1, samples);
    const flatPoints: MapPoint[] = [];
    const flatWidths: number[] = [];
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
        segmentIndices.push(index - 1);
      }
    }

    return this.withLengths(this.dedupe(flatPoints, flatWidths, segmentIndices));
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

  /** Точка края на дробном индексе сэмпла. */
  edgePoint(edge: MapPoint[], position: number): MapPoint {
    const index = Math.floor(position);
    const next = Math.min(edge.length - 1, index + 1);
    const t = Math.max(0, Math.min(1, position - index));
    const start = edge[Math.max(0, index)];

    return [
      start[0] + (edge[next][0] - start[0]) * t,
      start[1] + (edge[next][1] - start[1]) * t,
    ];
  }

  /** Точка осевой на дробном индексе сэмпла. */
  centerPoint(flat: RibbonFlat, position: number): MapPoint {
    return this.edgePoint(flat.points, position);
  }

  /** Детерминированные блики течения на момент времени. */
  flowMarks(
    line: MapLine,
    layout: RibbonLayout,
    timeMs: number,
    reducedMotion = false,
    scale = 1,
  ): FlowMark[] {
    const flow = line.decor?.flow;
    if (!flow?.enabled || layout.flat.totalLength < 4) return [];

    const flat = layout.flat;
    const random = this.seededRandom(flow.seed || line.id || 1);
    const spacing = Math.max(28, Math.min(220, 220 - flow.density * 1.6));
    const count = Math.max(1, Math.min(240, Math.ceil(flat.totalLength / spacing)));
    const opacity = Math.max(0, Math.min(1, flow.opacity / 100));
    const elapsed = reducedMotion ? 0 : timeMs / 1000;
    const marks: FlowMark[] = [];

    for (let index = 0; index < count; index += 1) {
      const phase = random();
      const lateral = (random() * 2 - 1) * 0.34;
      const length = 14 + random() * 30;
      const width = Math.max(1.2 / Math.max(0.2, scale), 1 + random() * 2.5);
      const brightness = 0.55 + random() * 0.45;
      const progress = ((phase + (elapsed * flow.speed * flow.direction) / flat.totalLength) % 1 + 1) % 1;
      const distance = progress * flat.totalLength;
      const position = this.positionAtDistance(flat, distance);
      const point = this.centerPoint(flat, position);
      const halfWidth = this.interpolateValue(flat.widths, position) / 2;
      const tangent = this.tangentAt(flat, position);
      const normal: MapPoint = [-tangent[1], tangent[0]];
      const shiftX = normal[0] * lateral * halfWidth;
      const shiftY = normal[1] * lateral * halfWidth;
      const halfLength = length / 2;
      marks.push({
        start: [
          point[0] + shiftX - tangent[0] * halfLength,
          point[1] + shiftY - tangent[1] * halfLength,
        ],
        end: [
          point[0] + shiftX + tangent[0] * halfLength,
          point[1] + shiftY + tangent[1] * halfLength,
        ],
        width,
        alpha: opacity * brightness,
      });
    }

    return marks;
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
    segmentIndices: number[],
  ): Omit<RibbonFlat, 'lengths' | 'totalLength'> {
    const keptPoints: MapPoint[] = [];
    const keptWidths: number[] = [];
    const keptSegments: number[] = [];
    points.forEach((point, index) => {
      const previous = keptPoints[keptPoints.length - 1];
      if (previous && Math.hypot(point[0] - previous[0], point[1] - previous[1]) < 1e-6) return;
      keptPoints.push([point[0], point[1]]);
      keptWidths.push(widths[index]);
      keptSegments.push(segmentIndices[index]);
    });

    return { points: keptPoints, widths: keptWidths, segmentIndices: keptSegments };
  }

  private interpolateValue(values: number[], position: number): number {
    const index = Math.floor(position);
    const next = Math.min(values.length - 1, index + 1);
    const t = Math.max(0, Math.min(1, position - index));

    return values[Math.max(0, index)] + (values[next] - values[Math.max(0, index)]) * t;
  }

  private tangentAt(flat: RibbonFlat, position: number): MapPoint {
    const index = Math.max(0, Math.min(flat.points.length - 1, Math.floor(position)));
    const start = flat.points[Math.max(0, index - 1)];
    const end = flat.points[Math.min(flat.points.length - 1, index + 1)];
    const deltaX = end[0] - start[0];
    const deltaY = end[1] - start[1];
    const length = Math.hypot(deltaX, deltaY);
    if (length < 1e-9) return [1, 0];

    return [deltaX / length, deltaY / length];
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
