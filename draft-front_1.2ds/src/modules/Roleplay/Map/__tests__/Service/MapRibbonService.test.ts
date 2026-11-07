import { describe, expect, it } from 'vitest';
import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';
import { MapRibbonService } from '@/modules/Roleplay/Map/Service/MapRibbonService';

function line(overrides: Partial<MapLine> = {}): MapLine {
  return {
    id: 1,
    variantId: 1,
    color: '#3f6f8f',
    speed: 400,
    points: [
      [0, 0],
      [100, 0],
      [100, 100],
    ],
    widths: [20, 20, 20],
    handles: [],
    segments: [],
    colorBlend: 16,
    decor: {},
    label: '',
    isPresent: true,
    sortOrder: 0,
    ...overrides,
  };
}

const service = new MapRibbonService(new MapGeometryService());

describe('MapRibbonService', () => {
  it('flattens a polyline and keeps the segment index of every sample', () => {
    const flat = service.flatten(line(), 4);

    expect(flat.points[0]).toEqual([0, 0]);
    expect(flat.points.at(-1)).toEqual([100, 100]);
    expect(flat.segmentIndices[0]).toBe(0);
    expect(flat.segmentIndices.at(-1)).toBe(1);
    expect(flat.totalLength).toBeGreaterThan(190);
  });

  it('merges equal neighbouring colours into one continuous run', () => {
    const flat = service.flatten(line(), 4);
    const runs = service.colorRuns(flat, line());

    expect(runs).toEqual([{ from: 0, to: flat.points.length - 1, color: '#3f6f8f' }]);
  });

  it('splits runs by effective segment colour and cuts a gradient gap', () => {
    const source = line({
      segments: [
        { speed: 400, color: null },
        { speed: 1000, color: '#b7a98a' },
      ],
    });
    const layout = service.layout(source, 8);

    expect(layout.runs).toHaveLength(2);
    expect(layout.transitions).toHaveLength(1);
    expect(layout.runs[0].color).toBe('#3f6f8f');
    expect(layout.runs[1].color).toBe('#b7a98a');
    expect(layout.runs[0].to).toBeLessThan(layout.runs[1].from);
    expect(layout.transitions[0].fromColor).toBe('#3f6f8f');
    expect(layout.transitions[0].toColor).toBe('#b7a98a');
  });

  it('keeps a hard border when colorBlend is zero', () => {
    const layout = service.layout(
      line({
        colorBlend: 0,
        segments: [
          { speed: 400, color: null },
          { speed: 1000, color: '#b7a98a' },
        ],
      }),
      8,
    );

    expect(layout.transitions).toEqual([]);
    expect(layout.runs[0].to).toBe(layout.runs[1].from);
  });

  it('builds continuous ribbon edges with a miter at the bend', () => {
    const flat = service.flatten(line(), 8);
    const edges = service.ribbonEdges(flat.points, flat.widths);

    expect(edges.left).toHaveLength(flat.points.length);
    expect(edges.right).toHaveLength(flat.points.length);
    expect(edges.left.every(([x, y]) => Number.isFinite(x) && Number.isFinite(y))).toBe(true);
    const middle = Math.round((flat.points.length - 1) / 2);
    expect(edges.left[middle][0]).not.toBe(edges.right[middle][0]);
  });

  it('interpolates an edge point between samples', () => {
    const edge: [number, number][] = [
      [0, 0],
      [10, 0],
      [20, 0],
    ];

    expect(service.edgePoint(edge, 0.5)).toEqual([5, 0]);
    expect(service.edgePoint(edge, 1.25)).toEqual([12.5, 0]);
  });

  it('lays out deterministic ripple particles and reuses the cache', () => {
    const source = line({
      decor: {
        flow: {
          enabled: true,
          speed: 40,
          density: 50,
          opacity: 20,
          color: '#ffffff',
          direction: 1,
          seed: 7,
        },
      },
    });
    const layout = service.layout(source, 8);
    const particles = service.flowParticles(source, layout);
    const again = service.flowParticles(source, layout);

    expect(particles.length).toBeGreaterThan(0);
    expect(again).toBe(particles);
    expect(new Set(particles.map((particle) => particle.color)).size).toBeGreaterThan(1);
    expect(particles.every((particle) => particle.color !== '#ffffff')).toBe(true);
  });

  it('moves glints over time and freezes them under reduced motion', () => {
    const source = line({
      decor: {
        flow: {
          enabled: true,
          speed: 40,
          density: 50,
          opacity: 20,
          color: '#ffffff',
          direction: 1,
          seed: 7,
        },
      },
    });
    const layout = service.layout(source, 8);
    const particles = service.flowParticles(source, layout);
    const first = service.flowGlints(particles, layout.flat, 1000, 40, 1);
    const again = service.flowGlints(particles, layout.flat, 1000, 40, 1);
    const later = service.flowGlints(particles, layout.flat, 3000, 40, 1);
    const atZero = service.flowGlints(particles, layout.flat, 0, 40, 1);
    const still = service.flowGlints(particles, layout.flat, 1000, 40, 1, true);

    expect(first.length).toBeGreaterThan(0);
    expect(again).toEqual(first);
    expect(later).not.toEqual(first);
    expect(still).toEqual(atZero);
  });

  it('fades each glint in and out while the position stays put', () => {
    const source = line({
      decor: {
        flow: {
          enabled: true,
          speed: 40,
          density: 50,
          opacity: 20,
          color: '#ffffff',
          direction: 1,
          seed: 7,
        },
      },
    });
    const layout = service.layout(source, 8);
    const particles = service.flowParticles(source, layout);
    const frames = [0, 700, 1400, 2100].map((time) =>
      service.flowGlints(particles, layout.flat, time, 0, 1).map((glint) => glint.alpha),
    );
    const flat = frames.flat();
    const sums = frames.map((frame) => Number(frame.reduce((sum, alpha) => sum + alpha, 0).toFixed(6)));

    expect(flat.length).toBeGreaterThan(0);
    expect(Math.min(...flat)).toBeLessThan(Math.max(...flat));
    expect(new Set(sums).size).toBeGreaterThan(1);
  });

  it('adds more particles as density grows', () => {
    const make = (density: number) =>
      line({
        decor: {
          flow: {
            enabled: true,
            speed: 40,
            density,
            opacity: 20,
            color: '#ffffff',
            direction: 1,
            seed: 7,
          },
        },
      });
    const sparse = service.layout(make(0), 8);
    const dense = service.layout(make(100), 8);

    expect(service.flowParticles(make(100), dense).length).toBeGreaterThan(
      service.flowParticles(make(0), sparse).length,
    );
  });

  it('draws no flow when the decor is disabled', () => {
    const source = line();
    const layout = service.layout(source, 8);

    expect(service.flowParticles(source, layout)).toEqual([]);
    expect(service.flowGlints([], layout.flat, 0, 40, 1)).toEqual([]);
  });
});
