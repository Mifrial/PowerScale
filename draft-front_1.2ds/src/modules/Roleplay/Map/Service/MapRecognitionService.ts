import type { MapHandle } from '@/modules/Roleplay/Map/Dto/MapHandle';
import { zeroMapHandle } from '@/modules/Roleplay/Map/Dto/MapHandle';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapRecognitionImage } from '@/modules/Roleplay/Map/Dto/MapRecognitionImage';
import type { MapRecognitionObject } from '@/modules/Roleplay/Map/Dto/MapRecognitionObject';
import type { MapRecognitionOptions } from '@/modules/Roleplay/Map/Dto/MapRecognitionOptions';
import type { MapRecognitionRegion } from '@/modules/Roleplay/Map/Dto/MapRecognitionRegion';
import type { MapRecognitionResult } from '@/modules/Roleplay/Map/Dto/MapRecognitionResult';
import { MAP_RECOGNITION } from '@/modules/Roleplay/Map/Constant/Map/MAP_RECOGNITION';
import type { MapGeometryService } from '@/modules/Roleplay/Map/Service/MapGeometryService';

/**
 * Распознаёт однотипные пятна на подложке по цвету.
 *
 * Работает по RGBA-пикселям, без DOM: маска → эрозия → водораздел по исходной
 * маске → контур по границам пикселей → упрощение Дугласом — Пекером →
 * аппроксимация плавных участков дугами Безье. Наружу отдаёт контуры в
 * пикселях подложки, поэтому сервис можно гонять в воркере и тестировать без
 * canvas.
 */
export class MapRecognitionService {
  constructor(private readonly geometry: MapGeometryService) {}

  /** Ищет пятна цвета образцов и возвращает принятые контуры. */
  detect(image: MapRecognitionImage, options: MapRecognitionOptions): MapRecognitionResult {
    const empty: MapRecognitionResult = {
      objects: [],
      found: 0,
      skippedSmall: 0,
      skippedLarge: 0,
      skippedThin: 0,
      meanVertices: 0,
      maxVertices: 0,
      mask: new Uint8Array(0),
    };
    if (image.width <= 0 || image.height <= 0 || image.data.length < image.width * image.height * 4) {
      return empty;
    }

    const samples = options.samples
      .map((color) => this.parseHex(color))
      .filter((sample): sample is [number, number, number] => sample !== null)
      .slice(0, MAP_RECOGNITION.limits.maxSamples);
    if (samples.length === 0) return empty;

    const tolerance = this.clamp(options.tolerance, MAP_RECOGNITION.limits.tolerance.min, MAP_RECOGNITION.limits.tolerance.max);
    const minArea = this.clamp(options.minArea, MAP_RECOGNITION.limits.minArea.min, MAP_RECOGNITION.limits.minArea.max);
    const maxArea = Math.max(0, options.maxArea);
    const gap = this.clamp(options.gap, MAP_RECOGNITION.limits.gap.min, MAP_RECOGNITION.limits.gap.max);
    const simplify = this.clamp(
      options.simplify,
      MAP_RECOGNITION.limits.simplify.min,
      MAP_RECOGNITION.limits.simplify.max,
    );
    const maxVertices = this.clamp(
      Math.round(options.maxVertices),
      MAP_RECOGNITION.limits.maxVertices.min,
      MAP_RECOGNITION.limits.maxVertices.max,
    );
    const smoothArcs = options.smoothArcs !== false;
    const region = this.regionOf(options.region, image.width, image.height);
    const mask = this.buildMask(image, samples, tolerance, options.ignoreLuminance, region);

    // Эрозия даёт семена, водораздел возвращает каждый пиксель исходной маски
    // ближайшему семени. Перемычки режутся по средней линии, а силуэт остаётся
    // исходным — без геометрического offset, который давал шипы на дугах.
    const labels = new Int32Array(image.width * image.height);
    const components = this.labelComponents(mask, image, labels, region, gap);

    // Превью показывает только пиксели, попавшие в компоненты: пиксели
    // съеденных эрозией тонких полосок остаются за кадром.
    for (let index = 0; index < mask.length; index += 1) {
      if (labels[index] === 0) mask[index] = 0;
    }

    const objects: MapRecognitionObject[] = [];
    let skippedSmall = 0;
    let skippedLarge = 0;
    let skippedThin = 0;
    let verticesSum = 0;
    let verticesMax = 0;

    for (let index = 0; index < components.length; index += 1) {
      const component = components[index];
      if (component.area < minArea) {
        skippedSmall += 1;
        continue;
      }
      if (maxArea > 0 && component.area > maxArea) {
        skippedLarge += 1;
        continue;
      }
      const label = index + 1;

      this.fillHoles(
        labels,
        image.width,
        label,
        component.minX,
        component.minY,
        component.maxX,
        component.maxY,
      );
      const loop = this.traceLargestLoop(
        labels,
        image.width,
        image.height,
        label,
        component.minX,
        component.minY,
        component.maxX,
        component.maxY,
      );
      if (loop.length < 4) continue;

      const simplified = this.simplifyToLimit(loop, simplify, maxVertices);
      if (simplified.length < 3) {
        // Контур выродился в линию: тонкая полоска не объект.
        skippedThin += 1;
        continue;
      }
      const points = this.repairPolygon(this.cleanPoints(simplified));
      if (points.length < 3) {
        skippedThin += 1;
        continue;
      }
      const shape = this.buildShape(points, maxVertices, smoothArcs, loop);

      objects.push({
        points: shape.points,
        handles: shape.handles,
        area: component.area,
        color: this.toHex(component.sumR / component.area, component.sumG / component.area, component.sumB / component.area),
      });
      verticesSum += shape.points.length;
      verticesMax = Math.max(verticesMax, shape.points.length);
    }

    return {
      objects,
      found: components.length,
      skippedSmall,
      skippedLarge,
      skippedThin,
      meanVertices: objects.length === 0 ? 0 : Math.round(verticesSum / objects.length),
      maxVertices: verticesMax,
      mask,
    };
  }

  /**
   * Размечает компоненты связности водоразделом.
   *
   * Семена — результат эрозии: слипшиеся пятна уже разошлись. Затем
   * multi-source BFS обходит исходную маску и отдаёт каждый пиксель
   * ближайшему семени. Так контур остаётся исходного размера, а перемычка
   * делится по средней линии.
   */
  private labelComponents(
    mask: Uint8Array,
    image: MapRecognitionImage,
    labels: Int32Array,
    region: MapRecognitionRegion,
    gap: number,
  ): ComponentStats[] {
    const width = image.width;
    const right = region.x + region.width;
    const bottom = region.y + region.height;
    const seeds = gap > 0 ? this.erodeMask(mask, image.width, image.height, region, gap) : mask;
    const queue = new Int32Array(region.width * region.height);
    const data = image.data;
    const stats: ComponentStats[] = [];

    const absorb = (component: ComponentStats, index: number): void => {
      const x = index % width;
      const y = (index - x) / width;
      const dataIndex = index * 4;
      component.area += 1;
      component.sumR += data[dataIndex];
      component.sumG += data[dataIndex + 1];
      component.sumB += data[dataIndex + 2];
      if (x < component.minX) component.minX = x;
      if (y < component.minY) component.minY = y;
      if (x > component.maxX) component.maxX = x;
      if (y > component.maxY) component.maxY = y;
    };

    let label = 0;
    for (let y = region.y; y < bottom; y += 1) {
      for (let x = region.x; x < right; x += 1) {
        const start = y * width + x;
        if (seeds[start] === 0 || labels[start] !== 0) continue;

        label += 1;
        const component: ComponentStats = {
          area: 0,
          minX: width,
          minY: image.height,
          maxX: 0,
          maxY: 0,
          sumR: 0,
          sumG: 0,
          sumB: 0,
        };
        stats.push(component);
        let head = 0;
        let tail = 1;
        queue[0] = start;
        labels[start] = label;
        while (head < tail) {
          const index = queue[head];
          head += 1;
          absorb(component, index);
          const currentX = index % width;
          const currentY = (index - currentX) / width;
          if (currentX > region.x) {
            const next = index - 1;
            if (seeds[next] !== 0 && labels[next] === 0) {
              labels[next] = label;
              queue[tail] = next;
              tail += 1;
            }
          }
          if (currentX + 1 < right) {
            const next = index + 1;
            if (seeds[next] !== 0 && labels[next] === 0) {
              labels[next] = label;
              queue[tail] = next;
              tail += 1;
            }
          }
          if (currentY > region.y) {
            const next = index - width;
            if (seeds[next] !== 0 && labels[next] === 0) {
              labels[next] = label;
              queue[tail] = next;
              tail += 1;
            }
          }
          if (currentY + 1 < bottom) {
            const next = index + width;
            if (seeds[next] !== 0 && labels[next] === 0) {
              labels[next] = label;
              queue[tail] = next;
              tail += 1;
            }
          }
        }
      }
    }

    if (gap <= 0) return stats;

    // Волна от всех семян одновременно: граница между пятнами проходит там,
    // где расстояния до семян равны.
    let head = 0;
    let tail = 0;
    for (let y = region.y; y < bottom; y += 1) {
      for (let x = region.x; x < right; x += 1) {
        const index = y * width + x;
        if (labels[index] !== 0) {
          queue[tail] = index;
          tail += 1;
        }
      }
    }
    while (head < tail) {
      const index = queue[head];
      head += 1;
      const id = labels[index];
      const component = stats[id - 1];
      const currentX = index % width;
      const currentY = (index - currentX) / width;
      let next = index - 1;
      if (currentX > region.x && mask[next] !== 0 && labels[next] === 0) {
        labels[next] = id;
        queue[tail] = next;
        tail += 1;
        absorb(component, next);
      }
      next = index + 1;
      if (currentX + 1 < right && mask[next] !== 0 && labels[next] === 0) {
        labels[next] = id;
        queue[tail] = next;
        tail += 1;
        absorb(component, next);
      }
      next = index - width;
      if (currentY > region.y && mask[next] !== 0 && labels[next] === 0) {
        labels[next] = id;
        queue[tail] = next;
        tail += 1;
        absorb(component, next);
      }
      next = index + width;
      if (currentY + 1 < bottom && mask[next] !== 0 && labels[next] === 0) {
        labels[next] = id;
        queue[tail] = next;
        tail += 1;
        absorb(component, next);
      }
    }

    return stats;
  }

  /**
   * Заливает внутренние дворы компонента.
   *
   * Фон, до которого нельзя дойти от рамки, — дырка. Контур снимается только
   * по внешней границе, и без заливки обход уходит во двор и возвращается,
   * давая самопересекающийся полигон. Чужие компоненты не перезаписываем.
   */
  private fillHoles(
    labels: Int32Array,
    width: number,
    label: number,
    minX: number,
    minY: number,
    maxX: number,
    maxY: number,
  ): void {
    const boxWidth = maxX - minX + 1;
    const boxHeight = maxY - minY + 1;
    if (boxWidth <= 2 || boxHeight <= 2) return;

    const seen = new Uint8Array(boxWidth * boxHeight);
    const stack: number[] = [];
    const push = (x: number, y: number): void => {
      if (x < minX || x > maxX || y < minY || y > maxY) return;
      const boxIndex = (y - minY) * boxWidth + (x - minX);
      if (seen[boxIndex] !== 0) return;
      const index = y * width + x;
      if (labels[index] !== 0) return;
      seen[boxIndex] = 1;
      stack.push(index);
    };

    for (let x = minX; x <= maxX; x += 1) {
      push(x, minY);
      push(x, maxY);
    }
    for (let y = minY; y <= maxY; y += 1) {
      push(minX, y);
      push(maxX, y);
    }

    const right = maxX;
    const bottom = maxY;
    while (stack.length > 0) {
      const index = stack.pop() as number;
      const x = index % width;
      const y = (index - x) / width;
      if (x > minX) push(x - 1, y);
      if (x < right) push(x + 1, y);
      if (y > minY) push(x, y - 1);
      if (y < bottom) push(x, y + 1);
    }

    for (let y = minY; y <= maxY; y += 1) {
      for (let x = minX; x <= maxX; x += 1) {
        const boxIndex = (y - minY) * boxWidth + (x - minX);
        if (seen[boxIndex] !== 0) continue;
        const index = y * width + x;
        if (labels[index] === 0) labels[index] = label;
      }
    }
  }

  /** Строит маску совпадения по образцам; вне области поиска — нули. */
  private buildMask(
    image: MapRecognitionImage,
    samples: [number, number, number][],
    tolerance: number,
    ignoreLuminance: boolean,
    region: MapRecognitionRegion,
  ): Uint8Array {
    const mask = new Uint8Array(image.width * image.height);
    const data = image.data;
    const channelLimit = tolerance * MAP_RECOGNITION.color.channelPerTolerance;
    const chromaLimit = tolerance * MAP_RECOGNITION.color.chromaPerTolerance;
    const right = region.x + region.width;
    const bottom = region.y + region.height;
    const prepared = samples.map(([red, green, blue]) => {
      const sum = red + green + blue;

      return {
        red,
        green,
        blue,
        max: Math.max(red, green, blue),
        chromaRed: sum === 0 ? 0 : red / sum,
        chromaGreen: sum === 0 ? 0 : green / sum,
      };
    });

    for (let y = region.y; y < bottom; y += 1) {
      for (let x = region.x; x < right; x += 1) {
        const index = y * image.width + x;
        const dataIndex = index * 4;
        const red = data[dataIndex];
        const green = data[dataIndex + 1];
        const blue = data[dataIndex + 2];
        for (const sample of prepared) {
          if (ignoreLuminance) {
            const sum = red + green + blue;
            if (sum === 0) continue;
            const pixelMax = Math.max(red, green, blue);
            if (sample.max >= MAP_RECOGNITION.color.darkSampleLevel && pixelMax < sample.max * MAP_RECOGNITION.color.minLuminanceShare) {
              continue;
            }
            const chromaRed = red / sum;
            const chromaGreen = green / sum;
            const chroma = Math.max(Math.abs(chromaRed - sample.chromaRed), Math.abs(chromaGreen - sample.chromaGreen));
            if (chroma <= chromaLimit) {
              mask[index] = 1;

              break;
            }
          } else {
            const difference = Math.max(
              Math.abs(red - sample.red),
              Math.abs(green - sample.green),
              Math.abs(blue - sample.blue),
            );
            if (difference <= channelLimit) {
              mask[index] = 1;

              break;
            }
          }
        }
      }
    }

    return mask;
  }

  /** Эрозия маски в копию: тонкие перешейки между пятнами исчезают. */
  private erodeMask(
    mask: Uint8Array,
    width: number,
    height: number,
    region: MapRecognitionRegion,
    gap: number,
  ): Uint8Array {
    let current = mask.slice();
    let target = new Uint8Array(mask.length);
    const left = region.x;
    const right = region.x + region.width - 1;
    const top = region.y;
    const bottom = region.y + region.height - 1;
    for (let pass = 0; pass < gap; pass += 1) {
      target.fill(0);
      for (let y = region.y; y <= bottom; y += 1) {
        for (let x = left; x <= right; x += 1) {
          const index = y * width + x;
          if (current[index] === 0) continue;
          if (x <= left || x >= right || y <= top || y >= bottom) continue;
          if (
            current[index - 1] === 0 ||
            current[index + 1] === 0 ||
            current[index - width] === 0 ||
            current[index + width] === 0
          ) {
            continue;
          }
          target[index] = 1;
        }
      }
      const swap = current;
      current = target;
      target = swap;
    }

    return current;
  }

  /** Обходит границу компонента и возвращает самый большой замкнутый контур. */
  private traceLargestLoop(
    labels: Int32Array,
    width: number,
    height: number,
    label: number,
    minX: number,
    minY: number,
    maxX: number,
    maxY: number,
  ): MapPoint[] {
    const cornerWidth = width + 1;
    const edges = new Map<number, number>();
    const add = (cornerX: number, cornerY: number, direction: number): void => {
      const key = cornerY * cornerWidth + cornerX;
      edges.set(key, (edges.get(key) ?? 0) | (1 << direction));
    };

    for (let y = minY; y <= maxY; y += 1) {
      for (let x = minX; x <= maxX; x += 1) {
        const index = y * width + x;
        if (labels[index] !== label) continue;
        if (y === 0 || labels[index - width] !== label) add(x, y, 0);
        if (x + 1 >= width || labels[index + 1] !== label) add(x + 1, y, 1);
        if (y + 1 >= height || labels[index + width] !== label) add(x + 1, y + 1, 2);
        if (x === 0 || labels[index - 1] !== label) add(x, y + 1, 3);
      }
    }
    if (edges.size === 0) return [];

    let best: MapPoint[] = [];
    let bestArea = 0;
    const totalEdges = edges.size;
    while (edges.size > 0) {
      let startKey = 0;
      let startBits = 0;
      for (const [key, bits] of edges) {
        if (bits !== 0) {
          startKey = key;
          startBits = bits;

          break;
        }
      }
      if (startBits === 0) break;

      let direction = 0;
      while ((startBits & (1 << direction)) === 0) direction += 1;
      let currentKey = startKey;
      let currentDirection = direction;
      const points: MapPoint[] = [];
      let isClosed = false;

      for (let step = 0; step <= totalEdges; step += 1) {
        const x = currentKey % cornerWidth;
        const y = (currentKey - x) / cornerWidth;
        points.push([x, y]);
        const bits = edges.get(currentKey) ?? 0;
        const remaining = bits & ~(1 << currentDirection);
        if (remaining === 0) {
          edges.delete(currentKey);
        } else {
          edges.set(currentKey, remaining);
        }

        let nextX = x;
        let nextY = y;
        if (currentDirection === 0) nextX += 1;
        else if (currentDirection === 1) nextY += 1;
        else if (currentDirection === 2) nextX -= 1;
        else nextY -= 1;

        const nextKey = nextY * cornerWidth + nextX;
        if (nextKey === startKey && points.length >= 4) {
          isClosed = true;

          break;
        }
        const nextBits = edges.get(nextKey) ?? 0;
        if (nextBits === 0) break;
        currentDirection = this.chooseDirection(nextBits, currentDirection);
        currentKey = nextKey;
      }

      if (!isClosed) continue;
      const area = Math.abs(this.signedArea(points));
      if (area > bestArea) {
        best = points;
        bestArea = area;
      }
    }

    return best;
  }

  /**
   * Поворачивает как можно правее, чтобы идти по внешней границе.
   *
   * Приоритет — правый поворот, потом прямой, потом левый. Прямой поворот
   * «дешевле» правого только на вид: на перекрёстке границы он уводит обход
   * на соседнюю петлю и рождает восьмёрку.
   */
  private chooseDirection(bits: number, incoming: number): number {
    let best = -1;
    let bestRank = 4;
    for (let direction = 0; direction < 4; direction += 1) {
      if ((bits & (1 << direction)) === 0) continue;
      const turn = (direction - incoming + 4) % 4;
      if (turn === 2) continue;
      const rank = turn === 1 ? 0 : turn === 0 ? 1 : 2;
      if (rank < bestRank) {
        bestRank = rank;
        best = direction;
      }
    }

    return best < 0 ? (incoming + 2) % 4 : best;
  }

  /** Упрощает контур, пока вершин не станет не больше потолка. */
  private simplifyToLimit(points: MapPoint[], tolerance: number, maxVertices: number): MapPoint[] {
    let current = tolerance;
    let result = this.simplifyClosed(points, current);
    while (result.length > maxVertices && current < MAP_RECOGNITION.simplify.maxTolerance) {
      current = current <= 0 ? MAP_RECOGNITION.defaults.simplify : current * MAP_RECOGNITION.simplify.growth;
      result = this.simplifyClosed(points, current);
    }

    return this.limitVertices(result, maxVertices);
  }

  /**
   * Жёстко срезает вершины до потолка.
   *
   * Дуглас — Пекер останавливается на предельном допуске и может оставить
   * больше вершин, чем разрешено. Убираем по одной ту, что меньше всего
   * меняет площадь, — форма пятна сохраняется.
   */
  private limitVertices(points: MapPoint[], maxVertices: number): MapPoint[] {
    if (points.length <= maxVertices) return points;
    const kept = points.map((point) => [point[0], point[1]] as MapPoint);
    while (kept.length > maxVertices) {
      let worstIndex = 0;
      let worstLoss = Number.POSITIVE_INFINITY;
      for (let index = 0; index < kept.length; index += 1) {
        const previous = kept[(index - 1 + kept.length) % kept.length];
        const current = kept[index];
        const next = kept[(index + 1) % kept.length];
        const loss = Math.abs(
          (current[0] - previous[0]) * (next[1] - previous[1]) -
            (current[1] - previous[1]) * (next[0] - previous[0]),
        );
        if (loss < worstLoss) {
          worstLoss = loss;
          worstIndex = index;
        }
      }
      kept.splice(worstIndex, 1);
    }

    return kept;
  }

  /**
   * Убирает самопересечения контура.
   *
   * У пятна с заливом-«клювом» обход границы может пройти по перешейку и
   * вернуться: полигон получается восьмёркой. В точке пересечения делим его
   * на две петли и оставляем большую — внешнюю.
   */
  private repairPolygon(points: MapPoint[], depth = 0): MapPoint[] {
    const count = points.length;
    if (count < 4 || depth > 6) return points;
    for (let first = 0; first < count; first += 1) {
      const firstNext = (first + 1) % count;
      for (let second = first + 2; second < count; second += 1) {
        const secondNext = (second + 1) % count;
        if (secondNext === first) continue;
        const crossing = this.segmentCrossing(points[first], points[firstNext], points[second], points[secondNext]);
        if (crossing === null) continue;

        const loopA = this.cleanPoints([crossing, ...points.slice(first + 1, second + 1)]);
        const tail = secondNext === 0 ? [] : points.slice(secondNext);
        const loopB = this.cleanPoints([crossing, ...tail, ...points.slice(0, first + 1)]);
        const kept = Math.abs(this.signedArea(loopA)) >= Math.abs(this.signedArea(loopB)) ? loopA : loopB;
        if (kept.length < 3) return [];

        return this.repairPolygon(kept, depth + 1);
      }
    }

    return points;
  }

  /** Точка строгого пересечения двух отрезков или null. */
  private segmentCrossing(a: MapPoint, b: MapPoint, c: MapPoint, d: MapPoint): MapPoint | null {
    const rx = b[0] - a[0];
    const ry = b[1] - a[1];
    const sx = d[0] - c[0];
    const sy = d[1] - c[1];
    const denominator = rx * sy - ry * sx;
    if (Math.abs(denominator) < 1e-9) return null;
    const t = ((c[0] - a[0]) * sy - (c[1] - a[1]) * sx) / denominator;
    const u = ((c[0] - a[0]) * ry - (c[1] - a[1]) * rx) / denominator;
    if (t <= 0 || t >= 1 || u <= 0 || u >= 1) return null;

    return [Math.round(a[0] + rx * t), Math.round(a[1] + ry * t)];
  }

  /**
   * Упрощает замкнутый контур.
   *
   * Дуглас — Пекер сохраняет первую и последнюю точки; для замкнутого контура
   * их надо свести в одну, иначе короткое ребро у старта может схлопнуть
   * маленькое пятно целиком.
   */
  private simplifyClosed(points: MapPoint[], tolerance: number): MapPoint[] {
    if (points.length < 4) return this.cleanPoints(points);
    const closed: MapPoint[] = [...points, [points[0][0], points[0][1]]];
    const simplified = this.geometry.simplifyPath(closed, tolerance);
    simplified.pop();

    return this.cleanPoints(simplified);
  }

  /**
   * Строит форму контура: полигон и ручки Безье для плавных участков.
   *
   * Углы (поворот круче `cornerDegrees`) остаются острыми, участки между ними
   * проверяются на окружность. Если фит сошёлся, участок пересобирается
   * сегментами не длиннее 90°; иначе остаётся прямым.
   */
  private buildShape(
    points: MapPoint[],
    maxVertices: number,
    smoothArcs: boolean,
    loop: MapPoint[],
  ): { points: MapPoint[]; handles: MapHandle[] } {
    const straight = (): { points: MapPoint[]; handles: MapHandle[] } => ({
      points,
      handles: points.map(() => zeroMapHandle()),
    });
    if (!smoothArcs || points.length < 4) return straight();

    const smoothed = this.approximateArcs(points, loop);
    if (
      smoothed === null ||
      smoothed.points.length < 3 ||
      smoothed.points.length > maxVertices ||
      !this.fitsLoop(smoothed, loop)
    ) {
      return straight();
    }

    return smoothed;
  }

  /** Сглаженные сегменты не должны уходить с исходного контура. */
  private fitsLoop(shape: { points: MapPoint[]; handles: MapHandle[] }, loop: MapPoint[]): boolean {
    const limit = MAP_RECOGNITION.arc.contourDeviation;
    for (let index = 0; index < shape.points.length; index += 1) {
      const next = (index + 1) % shape.points.length;
      const startOut = shape.handles[index].out;
      const endIn = shape.handles[next].in;
      if (startOut[0] === 0 && startOut[1] === 0 && endIn[0] === 0 && endIn[1] === 0) continue;

      const start = shape.points[index];
      const end = shape.points[next];
      const controlOne: MapPoint = [start[0] + startOut[0], start[1] + startOut[1]];
      const controlTwo: MapPoint = [end[0] + endIn[0], end[1] + endIn[1]];
      for (const t of [0.25, 0.5, 0.75]) {
        const point = this.geometry.cubicPoint(start, controlOne, controlTwo, end, t);
        if (this.distanceToLoop(point, loop) > limit) return false;
      }
    }

    return true;
  }

  /** Расстояние от точки до ближайшего ребра сырого контура. */
  private distanceToLoop(point: MapPoint, loop: MapPoint[]): number {
    let nearest = Number.POSITIVE_INFINITY;
    for (let index = 0; index < loop.length; index += 1) {
      const distance = this.geometry.distanceToSegment(point, loop[index], loop[(index + 1) % loop.length]);
      if (distance < nearest) nearest = distance;
    }

    return nearest;
  }

  /** Разбивает контур на участки между углами и заменяет дуги безье. */
  private approximateArcs(points: MapPoint[], loop: MapPoint[]): { points: MapPoint[]; handles: MapHandle[] } | null {
    // Круг без углов собирается из четырёх сегментов; пробуем его первым,
    // чтобы лесенка пикселей не дробила фигуру на мнимые углы.
    const circle = this.approximateFullCircle(points, loop);
    if (circle !== null) return circle;

    const cornerIndices: number[] = [];
    const flags = this.cornerFlags(points);
    for (let index = 0; index < points.length; index += 1) {
      if (flags[index]) cornerIndices.push(index);
    }
    if (cornerIndices.length < 2) return null;

    const resultPoints: MapPoint[] = [];
    const resultHandles: MapHandle[] = [];
    let pendingIn: MapPoint = [0, 0];

    for (let runIndex = 0; runIndex < cornerIndices.length; runIndex += 1) {
      const start = cornerIndices[runIndex];
      const end = cornerIndices[(runIndex + 1) % cornerIndices.length];
      resultPoints.push([points[start][0], points[start][1]]);
      resultHandles.push({ in: pendingIn, out: [0, 0] });

      const run = this.collectRun(points, start, end);
      const arc = this.fitRunArc(run);
      if (arc !== null) {
        resultHandles[resultHandles.length - 1].out = arc.startOut;
        for (const item of arc.points) {
          resultPoints.push(item.point);
          resultHandles.push({ in: item.in, out: item.out });
        }
        pendingIn = arc.endIn;

        continue;
      }

      // Дуга не сошлась: сохраняем промежуточные вершины полигона как есть.
      let index = (start + 1) % points.length;
      while (index !== end) {
        resultPoints.push([points[index][0], points[index][1]]);
        resultHandles.push({ in: [0, 0], out: [0, 0] });
        index = (index + 1) % points.length;
      }
      pendingIn = [0, 0];
    }

    resultHandles[0].in = pendingIn;

    return { points: resultPoints, handles: resultHandles };
  }

  /** Замкнутая плавная фигура без углов: круг собирается из четырёх сегментов. */
  private approximateFullCircle(
    points: MapPoint[],
    loop: MapPoint[],
  ): { points: MapPoint[]; handles: MapHandle[] } | null {
    if (points.length < 6) return null;
    const fit = this.fitCircle(points);
    if (fit === null) return null;
    const sweep = this.totalSweep(points, fit);
    if (sweep === null || Math.abs(Math.abs(sweep) - Math.PI * 2) > 0.7) return null;
    // Вершины прямоугольника тоже лежат на его описанной окружности: без
    // проверки сырого контура прямой край «схлопнулся» бы в дугу.
    for (const [x, y] of loop) {
      if (Math.abs(Math.hypot(x - fit.cx, y - fit.cy) - fit.radius) > MAP_RECOGNITION.arc.contourDeviation) return null;
    }

    // Полный круг — ровно четыре безье-сегмента по 90°.
    const segments = 4;
    const step = sweep / segments;
    const direction = Math.sign(sweep);
    const startAngle = Math.atan2(points[0][1] - fit.cy, points[0][0] - fit.cx);
    const radius = fit.radius;
    const k = (4 / 3) * Math.tan(Math.abs(step) / 4) * radius;
    if (!Number.isFinite(k) || k <= 0) return null;

    const resultPoints: MapPoint[] = [];
    const resultHandles: MapHandle[] = [];
    for (let index = 0; index < segments; index += 1) {
      const angle = startAngle + step * index;
      const point: MapPoint = [
        Math.round(fit.cx + radius * Math.cos(angle)),
        Math.round(fit.cy + radius * Math.sin(angle)),
      ];
      const tangent = this.arcTangent(angle, direction);
      resultPoints.push(point);
      resultHandles.push({
        in: [-tangent[0] * k, -tangent[1] * k],
        out: [tangent[0] * k, tangent[1] * k],
      });
    }

    return { points: resultPoints, handles: resultHandles };
  }

  /** Пробует заменить участок между углами дугой окружности. */
  private fitRunArc(run: MapPoint[]): {
    points: { point: MapPoint; in: MapPoint; out: MapPoint }[];
    startOut: MapPoint;
    endIn: MapPoint;
  } | null {
    // Три-четыре точки легко «подогнать» под любую окружность: для дуги
    // нужен запас узлов, иначе прямой угол или выемка станут кривой.
    if (run.length < 5) return null;
    const fit = this.fitCircle(run);
    if (fit === null) return null;
    const sweep = this.totalSweep(run, fit);
    if (sweep === null || Math.abs(sweep) < MAP_RECOGNITION.arc.minSweep) return null;

    const segments = Math.max(1, Math.ceil(Math.abs(sweep) / MAP_RECOGNITION.arc.maxSegment - 0.05));
    // Новых вершин не добавляем: дуга должна хотя бы не усложнять контур.
    if (segments - 1 > run.length - 2) return null;

    const step = sweep / segments;
    const direction = Math.sign(sweep);
    const startAngle = Math.atan2(run[0][1] - fit.cy, run[0][0] - fit.cx);
    const radius = fit.radius;
    const k = (4 / 3) * Math.tan(Math.abs(step) / 4) * radius;
    if (!Number.isFinite(k) || k <= 0) return null;

    const points: { point: MapPoint; in: MapPoint; out: MapPoint }[] = [];
    for (let index = 1; index < segments; index += 1) {
      const angle = startAngle + step * index;
      const point: MapPoint = [
        Math.round(fit.cx + radius * Math.cos(angle)),
        Math.round(fit.cy + radius * Math.sin(angle)),
      ];
      const tangent = this.arcTangent(angle, direction);
      points.push({
        point,
        in: [-tangent[0] * k, -tangent[1] * k],
        out: [tangent[0] * k, tangent[1] * k],
      });
    }
    const startTangent = this.arcTangent(startAngle, direction);
    const endTangent = this.arcTangent(startAngle + sweep, direction);

    return {
      points,
      startOut: [startTangent[0] * k, startTangent[1] * k],
      endIn: [-endTangent[0] * k, -endTangent[1] * k],
    };
  }

  /** Какие вершины — углы здания. */
  private cornerFlags(points: MapPoint[]): boolean[] {
    const count = points.length;
    const flags = new Array<boolean>(count).fill(false);
    const limit = Math.cos((MAP_RECOGNITION.arc.cornerDegrees * Math.PI) / 180);
    for (let index = 0; index < count; index += 1) {
      const previous = points[(index - 1 + count) % count];
      const current = points[index];
      const next = points[(index + 1) % count];
      const ax = current[0] - previous[0];
      const ay = current[1] - previous[1];
      const bx = next[0] - current[0];
      const by = next[1] - current[1];
      const lengthA = Math.hypot(ax, ay);
      const lengthB = Math.hypot(bx, by);
      if (lengthA < 1e-6 || lengthB < 1e-6) continue;
      const cosine = (ax * bx + ay * by) / (lengthA * lengthB);
      if (cosine < limit) flags[index] = true;
    }

    return flags;
  }

  /** Вершины участка от угла `start` до угла `end` включительно. */
  private collectRun(points: MapPoint[], start: number, end: number): MapPoint[] {
    const run: MapPoint[] = [];
    let index = start;
    for (let guard = 0; guard <= points.length; guard += 1) {
      run.push(points[index]);
      if (index === end) break;
      index = (index + 1) % points.length;
    }

    return run;
  }

  /** Окружность по методу наименьших квадратов; null — точки не на окружности. */
  private fitCircle(points: MapPoint[]): { cx: number; cy: number; radius: number } | null {
    const count = points.length;
    if (count < 3) return null;
    let meanX = 0;
    let meanY = 0;
    for (const [x, y] of points) {
      meanX += x;
      meanY += y;
    }
    meanX /= count;
    meanY /= count;

    // z = 2a·x + 2b·y + c, где (a, b) — центр относительно среднего.
    let sxx = 0;
    let sxy = 0;
    let syy = 0;
    let sx = 0;
    let sy = 0;
    let sxz = 0;
    let syz = 0;
    let sz = 0;
    for (const [pointX, pointY] of points) {
      const x = pointX - meanX;
      const y = pointY - meanY;
      const z = x * x + y * y;
      sxx += x * x;
      sxy += x * y;
      syy += y * y;
      sx += x;
      sy += y;
      sxz += x * z;
      syz += y * z;
      sz += z;
    }
    const determinant =
      sxx * (syy * count - sy * sy) - sxy * (sxy * count - sy * sx) + sx * (sxy * sy - syy * sx);
    if (Math.abs(determinant) < 1e-6) return null;

    const a =
      (sxz * (syy * count - sy * sy) - sxy * (syz * count - sy * sz) + sx * (syz * sy - syy * sz)) /
      determinant;
    const b =
      (sxx * (syz * count - sy * sz) - sxz * (sxy * count - sy * sx) + sx * (sxy * sz - syz * sx)) /
      determinant;
    const c =
      (sxx * (syy * sz - syz * sy) - sxy * (sxy * sz - syz * sx) + sxz * (sxy * sy - syy * sx)) /
      determinant;
    const radiusSquared = c + (a * a) / 4 + (b * b) / 4;
    if (!Number.isFinite(radiusSquared) || radiusSquared <= 0) return null;
    const radius = Math.sqrt(radiusSquared);
    if (radius < MAP_RECOGNITION.arc.minRadius) return null;

    const cx = meanX + a / 2;
    const cy = meanY + b / 2;
    for (const [x, y] of points) {
      if (Math.abs(Math.hypot(x - cx, y - cy) - radius) > MAP_RECOGNITION.arc.deviation) return null;
    }

    return { cx, cy, radius };
  }

  /** Суммарный разворот точек вокруг центра; null — участок меняет направление. */
  private totalSweep(points: MapPoint[], fit: { cx: number; cy: number }): number | null {
    let total = 0;
    let previous = Math.atan2(points[0][1] - fit.cy, points[0][0] - fit.cx);
    for (let index = 1; index < points.length; index += 1) {
      const angle = Math.atan2(points[index][1] - fit.cy, points[index][0] - fit.cx);
      let delta = angle - previous;
      while (delta > Math.PI) delta -= Math.PI * 2;
      while (delta < -Math.PI) delta += Math.PI * 2;
      if (total !== 0 && Math.sign(delta) !== Math.sign(total) && Math.abs(delta) > 0.25) return null;
      total += delta;
      previous = angle;
    }

    return total;
  }

  /** Единичная касательная окружности в точке, с учётом направления обхода. */
  private arcTangent(angle: number, direction: number): MapPoint {
    return [-Math.sin(angle) * direction, Math.cos(angle) * direction];
  }

  /** Округляет точки, убирает дубликаты и схлопывает коллинеарные вершины. */
  private cleanPoints(points: MapPoint[]): MapPoint[] {
    const rounded: MapPoint[] = [];
    for (const [x, y] of points) {
      const point: MapPoint = [Math.round(x), Math.round(y)];
      const last = rounded[rounded.length - 1];
      if (last && last[0] === point[0] && last[1] === point[1]) continue;
      rounded.push(point);
    }
    if (rounded.length > 1) {
      const first = rounded[0];
      const last = rounded[rounded.length - 1];
      if (first[0] === last[0] && first[1] === last[1]) rounded.pop();
    }
    if (rounded.length < 3) return rounded;

    const result: MapPoint[] = [];
    for (let index = 0; index < rounded.length; index += 1) {
      const previous = result[result.length - 1];
      const current = rounded[index];
      const next = rounded[(index + 1) % rounded.length];
      if (!previous) {
        result.push(current);

        continue;
      }
      const cross =
        (current[0] - previous[0]) * (next[1] - current[1]) -
        (current[1] - previous[1]) * (next[0] - current[0]);
      if (Math.abs(cross) <= 0.01) continue;
      result.push(current);
    }

    return result;
  }

  /** Удвоенная площадь контура со знаком: плюс — обход по часовой в экранных координатах. */
  private signedArea(points: MapPoint[]): number {
    let sum = 0;
    for (let index = 0; index < points.length; index += 1) {
      const current = points[index];
      const next = points[(index + 1) % points.length];
      sum += current[0] * next[1] - next[0] * current[1];
    }

    return sum / 2;
  }

  /** Рамка поиска, прижатая к изображению. */
  private regionOf(region: MapRecognitionRegion | null, width: number, height: number): MapRecognitionRegion {
    if (region === null) return { x: 0, y: 0, width, height };
    const x = Math.max(0, Math.min(width, Math.round(region.x)));
    const y = Math.max(0, Math.min(height, Math.round(region.y)));
    const right = Math.max(x, Math.min(width, Math.round(region.x + region.width)));
    const bottom = Math.max(y, Math.min(height, Math.round(region.y + region.height)));

    return { x, y, width: right - x, height: bottom - y };
  }

  /** Разбирает `#rrggbb` в каналы. */
  private parseHex(color: string): [number, number, number] | null {
    const match = /^#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/.exec(color.trim());
    if (!match) return null;

    return [Number.parseInt(match[1], 16), Number.parseInt(match[2], 16), Number.parseInt(match[3], 16)];
  }

  /** Собирает `#rrggbb` из каналов. */
  private toHex(red: number, green: number, blue: number): string {
    const channel = (value: number): string => Math.max(0, Math.min(255, Math.round(value))).toString(16).padStart(2, '0');

    return `#${channel(red)}${channel(green)}${channel(blue)}`;
  }

  private clamp(value: number, min: number, max: number): number {
    if (!Number.isFinite(value)) return min;

    return Math.max(min, Math.min(max, value));
  }
}

/** Накопленная статистика компонента связности. */
interface ComponentStats {
  area: number;
  minX: number;
  minY: number;
  maxX: number;
  maxY: number;
  sumR: number;
  sumG: number;
  sumB: number;
}
