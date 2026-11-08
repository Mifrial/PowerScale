import { describe, expect, it } from 'vitest';
import type { MapRecognitionImage } from '@/modules/Roleplay/Map/Dto/MapRecognitionImage';
import type { MapRecognitionObject } from '@/modules/Roleplay/Map/Dto/MapRecognitionObject';
import type { MapRecognitionOptions } from '@/modules/Roleplay/Map/Dto/MapRecognitionOptions';
import { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';
import { MapRecognitionService } from '@/modules/Roleplay/Map/Service/MapRecognitionService';

const service = new MapRecognitionService(new MapGeometryService());

const BROWN: [number, number, number] = [139, 107, 74];
const WHITE: [number, number, number] = [255, 255, 255];

function paint(
  width: number,
  height: number,
  colorOf: (x: number, y: number) => [number, number, number] | null,
): MapRecognitionImage {
  const data = new Uint8ClampedArray(width * height * 4);
  for (let y = 0; y < height; y += 1) {
    for (let x = 0; x < width; x += 1) {
      const index = (y * width + x) * 4;
      const color = colorOf(x, y) ?? WHITE;
      data[index] = color[0];
      data[index + 1] = color[1];
      data[index + 2] = color[2];
      data[index + 3] = 255;
    }
  }

  return { width, height, data };
}

/** Проверяет, что у полигона нет самопересечений. */
function isSimplePolygon(points: [number, number][]): boolean {
  const count = points.length;
  const cross = (a: [number, number], b: [number, number], c: [number, number]): number =>
    (b[0] - a[0]) * (c[1] - a[1]) - (b[1] - a[1]) * (c[0] - a[0]);
  for (let first = 0; first < count; first += 1) {
    for (let second = first + 2; second < count; second += 1) {
      if (first === 0 && second === count - 1) continue;
      const a = points[first];
      const b = points[(first + 1) % count];
      const c = points[second];
      const d = points[(second + 1) % count];
      const d1 = cross(c, d, a);
      const d2 = cross(c, d, b);
      const d3 = cross(a, b, c);
      const d4 = cross(a, b, d);
      if (d1 * d2 < 0 && d3 * d4 < 0) return false;
    }
  }

  return true;
}

function options(overrides: Partial<MapRecognitionOptions> = {}): MapRecognitionOptions {
  return {
    samples: ['#8b6b4a'],
    tolerance: 5,
    ignoreLuminance: false,
    minArea: 10,
    maxArea: 0,
    gap: 0,
    simplify: 1,
    maxVertices: 24,
    smoothArcs: false,
    region: null,
    ...overrides,
  };
}

function square(
  width: number,
  height: number,
  left: number,
  top: number,
  size: number,
  color: [number, number, number] = BROWN,
): MapRecognitionImage {
  return paint(width, height, (x, y) =>
    x >= left && x < left + size && y >= top && y < top + size ? color : null,
  );
}

const geometry = new MapGeometryService();

/** Площадь замкнутого контура. */
function polygonArea(points: [number, number][]): number {
  let sum = 0;
  for (let index = 0; index < points.length; index += 1) {
    const next = points[(index + 1) % points.length];
    sum += points[index][0] * next[1] - next[0] * points[index][1];
  }

  return Math.abs(sum) / 2;
}

/** Площадь фигуры с учётом ручек Безье. */
function flattenedArea(object: MapRecognitionObject): number {
  return polygonArea(geometry.flattenContour(object.points, object.handles, 24));
}

/** Нет ли ненулевых ручек. */
function isStraight(object: MapRecognitionObject): boolean {
  return object.handles.every(
    (handle) => handle.in[0] === 0 && handle.in[1] === 0 && handle.out[0] === 0 && handle.out[1] === 0,
  );
}

describe('MapRecognitionService', () => {
  it('finds a square as one four-point contour', () => {
    const result = service.detect(square(60, 60, 20, 20, 20), options());

    expect(result.found).toBe(1);
    expect(result.objects).toHaveLength(1);
    expect(result.objects[0].area).toBe(400);
    expect(result.objects[0].color).toBe('#8b6b4a');
    expect(result.objects[0].points.length).toBeGreaterThanOrEqual(4);
    expect(result.objects[0].points.length).toBeLessThanOrEqual(6);
    expect(result.maxVertices).toBe(result.objects[0].points.length);
  });

  it('keeps two separate squares apart', () => {
    const image = paint(80, 60, (x, y) => {
      if (y >= 10 && y < 30 && x >= 10 && x < 30) return BROWN;
      if (y >= 10 && y < 30 && x >= 40 && x < 60) return BROWN;

      return null;
    });
    const result = service.detect(image, options());

    expect(result.objects).toHaveLength(2);
  });

  it('splits squares joined by a one-pixel bridge only with a gap', () => {
    const image = paint(80, 60, (x, y) => {
      if (y >= 10 && y < 30 && x >= 10 && x < 30) return BROWN;
      if (y >= 10 && y < 30 && x >= 32 && x < 52) return BROWN;
      if (y === 19 && x === 30) return BROWN;
      if (y === 19 && x === 31) return BROWN;

      return null;
    });

    expect(service.detect(image, options()).objects).toHaveLength(1);
    expect(service.detect(image, options({ gap: 1 })).objects).toHaveLength(2);
  });

  it('fills an inner courtyard and keeps only the outer contour', () => {
    const image = paint(40, 40, (x, y) => {
      const inside = x >= 10 && x < 30 && y >= 10 && y < 30;
      const hole = x >= 16 && x < 24 && y >= 16 && y < 24;

      return inside && !hole ? BROWN : null;
    });
    const result = service.detect(image, options());

    expect(result.objects).toHaveLength(1);
    expect(result.objects[0].area).toBe(336);
    expect(result.objects[0].points.length).toBeLessThanOrEqual(6);
  });

  it('keeps a courtyard with a channel as a simple outer contour', () => {
    const image = paint(40, 40, (x, y) => {
      const inside = x >= 10 && x < 30 && y >= 10 && y < 30;
      const hole = x >= 16 && x < 24 && y >= 16 && y < 24;
      const channel = y === 20 && x >= 15 && x < 16;

      return inside && (!hole || channel) ? BROWN : null;
    });
    const result = service.detect(image, options());

    expect(result.objects).toHaveLength(1);
    expect(isSimplePolygon(result.objects[0].points)).toBe(true);
  });

  it('drops a one-pixel sliver instead of emitting a degenerate polygon', () => {
    const image = paint(100, 100, (x, y) => {
      const step = Math.floor((y - 10) / 2);

      return y >= 10 && y < 90 && x >= 10 + step && x < 12 + step ? BROWN : null;
    });
    const result = service.detect(image, options({ minArea: 5, simplify: 3 }));

    expect(result.objects).toHaveLength(0);
    expect(result.skippedThin).toBe(1);
  });

  it('drops noise below the minimum area and huge blobs above the maximum', () => {
    const image = paint(60, 60, (x, y) => {
      if (x >= 2 && x < 4 && y >= 2 && y < 4) return BROWN;
      if (x >= 10 && x < 50 && y >= 10 && y < 50) return BROWN;

      return null;
    });

    const small = service.detect(image, options({ minArea: 20, maxArea: 1000 }));
    expect(small.objects).toHaveLength(0);
    expect(small.skippedSmall).toBe(1);
    expect(small.skippedLarge).toBe(1);

    const all = service.detect(image, options({ minArea: 1, maxArea: 0 }));
    expect(all.objects).toHaveLength(2);
  });

  it('matches a darker shade when luminance is ignored', () => {
    const dark: [number, number, number] = [96, 72, 48];
    const image = square(60, 60, 20, 20, 20, dark);

    expect(service.detect(image, options()).objects).toHaveLength(0);
    expect(service.detect(image, options({ ignoreLuminance: true })).objects).toHaveLength(1);
  });

  it('never exceeds the vertex cap on a jagged contour', () => {
    const center = 45;
    const image = paint(90, 90, (x, y) => {
      const distance = Math.hypot(x - center, y - center);
      const angle = Math.atan2(y - center, x - center);
      const radius = 24 + Math.sin(angle * 12) * 8;

      return distance <= radius ? BROWN : null;
    });
    const result = service.detect(image, options({ simplify: 0.5, maxVertices: 8 }));

    expect(result.objects).toHaveLength(1);
    expect(result.objects[0].points.length).toBeGreaterThanOrEqual(3);
    expect(result.objects[0].points.length).toBeLessThanOrEqual(8);
  });

  it('limits the search to the requested pixel region', () => {
    const image = paint(80, 40, (x, y) => {
      if (y >= 10 && y < 30 && x >= 5 && x < 25) return BROWN;
      if (y >= 10 && y < 30 && x >= 45 && x < 65) return BROWN;

      return null;
    });
    const result = service.detect(image, options({ region: { x: 40, y: 0, width: 40, height: 40 } }));

    expect(result.objects).toHaveLength(1);
    expect(result.objects[0].area).toBe(400);
  });

  it('returns an empty result for an unknown sample colour', () => {
    const result = service.detect(square(20, 20, 5, 5, 10), options({ samples: ['nope'] }));

    expect(result.objects).toHaveLength(0);
    expect(result.found).toBe(0);
  });
});

describe('MapRecognitionService shapes', () => {
  it('keeps a rotated rectangle rectangular without smoothing its corners', () => {
    const image = paint(90, 90, (x, y) => {
      const dx = x - 45;
      const dy = y - 45;
      const angle = -0.4;
      const localX = dx * Math.cos(angle) - dy * Math.sin(angle);
      const localY = dx * Math.sin(angle) + dy * Math.cos(angle);

      return Math.abs(localX) <= 25 && Math.abs(localY) <= 10 ? BROWN : null;
    });
    const object = service.detect(image, options({ smoothArcs: true, gap: 1 })).objects[0];

    expect(object).toBeDefined();
    expect(object.points.length).toBeLessThanOrEqual(8);
    expect(isStraight(object)).toBe(true);
    expect(Math.abs(flattenedArea(object) - object.area) / object.area).toBeLessThan(0.1);
    expect(isSimplePolygon(object.points)).toBe(true);
  });

  it('rebuilds a semicircle as two bezier segments', () => {
    const image = paint(120, 120, (x, y) => {
      const dx = x - 60;
      const dy = y - 60;

      return dy <= 0 && Math.hypot(dx, dy) <= 35 ? BROWN : null;
    });
    const object = service.detect(image, options({ smoothArcs: true, gap: 1 })).objects[0];

    expect(object.points).toHaveLength(3);
    expect(object.handles.some((handle) => Math.hypot(handle.out[0], handle.out[1]) > 1)).toBe(true);
    expect(Math.abs(flattenedArea(object) - object.area) / object.area).toBeLessThan(0.1);
  });

  it('rebuilds a full circle from four bezier segments', () => {
    const image = paint(120, 120, (x, y) => (Math.hypot(x - 60, y - 60) <= 32 ? BROWN : null));
    const object = service.detect(image, options({ smoothArcs: true, gap: 1 })).objects[0];

    expect(object.points).toHaveLength(4);
    const flattened = geometry.flattenContour(object.points, object.handles, 24);
    for (const [x, y] of flattened) {
      expect(Math.abs(Math.hypot(x - 60, y - 60) - 32)).toBeLessThan(3);
    }
    expect(Math.abs(flattenedArea(object) - object.area) / object.area).toBeLessThan(0.1);
  });

  it('keeps an L-shape corners sharp', () => {
    const image = paint(80, 80, (x, y) => {
      const horizontal = x >= 20 && x < 56 && y >= 20 && y < 32;
      const vertical = x >= 20 && x < 32 && y >= 20 && y < 56;

      return horizontal || vertical ? BROWN : null;
    });
    const object = service.detect(image, options({ smoothArcs: true, gap: 1 })).objects[0];

    expect(object.points).toHaveLength(6);
    expect(isStraight(object)).toBe(true);
    expect(Math.abs(flattenedArea(object) - object.area) / object.area).toBeLessThan(0.1);
  });

  it('smooths a curved wall without distorting its silhouette', () => {
    const image = paint(140, 140, (x, y) => {
      const distance = Math.hypot(x - 70, y - 70);
      const angle = Math.atan2(y - 70, x - 70);

      return distance >= 30 && distance <= 34 && angle > Math.PI * 0.7 && angle < Math.PI * 1.3 ? BROWN : null;
    });
    const object = service.detect(image, options({ smoothArcs: true, gap: 1 })).objects[0];

    expect(object).toBeDefined();
    expect(Math.abs(flattenedArea(object) - object.area) / object.area).toBeLessThan(0.1);
    expect(isSimplePolygon(geometry.flattenContour(object.points, object.handles, 8))).toBe(true);
  });

  it('returns a plain polygon when smoothing is off', () => {
    const image = paint(120, 120, (x, y) => (Math.hypot(x - 60, y - 60) <= 32 ? BROWN : null));
    const object = service.detect(image, options({ smoothArcs: false, gap: 1 })).objects[0];

    expect(object.points.length).toBeGreaterThan(4);
    expect(isStraight(object)).toBe(true);
  });

  it('keeps the vertex cap and a simple outline on a jagged contour', () => {
    const image = paint(90, 90, (x, y) => {
      const distance = Math.hypot(x - 45, y - 45);
      const angle = Math.atan2(y - 45, x - 45);

      return distance <= 24 + Math.sin(angle * 12) * 8 ? BROWN : null;
    });
    const result = service.detect(image, options({ smoothArcs: true, simplify: 0.5, maxVertices: 8 }));

    expect(result.objects).toHaveLength(1);
    expect(result.objects[0].points.length).toBeLessThanOrEqual(8);
    expect(isSimplePolygon(result.objects[0].points)).toBe(true);
  });
});

