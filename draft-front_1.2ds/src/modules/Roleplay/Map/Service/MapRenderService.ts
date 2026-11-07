import type { MapArea } from '@/modules/Roleplay/Map/Dto/MapArea';
import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import { MAP_FILL_OPACITY } from '@/modules/Roleplay/Map/Constant/Map/MAP_FILL_OPACITY';
import { MAP_LINE_DEPTH } from '@/modules/Roleplay/Map/Constant/Map/MAP_LINE_DEPTH';

/** Разрешает визуальные настройки объекта с наследованием от карты. */
export class MapRenderService {
  /** Плотность заливки ленты: своя, иначе карты. */
  lineFillOpacity(line: MapLine, mapOpacity: number): number {
    return this.fillOpacity(line.decor?.fillOpacity, mapOpacity);
  }

  /** Плотность заливки области: своя, иначе карты. */
  areaFillOpacity(area: MapArea, mapOpacity: number): number {
    return this.fillOpacity(area.decor?.fillOpacity, mapOpacity);
  }

  /** Глубина русла ленты, 0–100. */
  lineDepth(line: MapLine): number {
    return this.clamp(line.decor?.depth, MAP_LINE_DEPTH.max, 0, 0);
  }

  /** Ширина профиля глубины, 20–100 %. */
  lineDepthWidth(line: MapLine): number {
    return this.clamp(
      line.decor?.depthWidth,
      MAP_LINE_DEPTH.max,
      MAP_LINE_DEPTH.minWidthPercent,
      MAP_LINE_DEPTH.defaultWidth,
    );
  }

  /** Плотность заливки с переопределением объекта. */
  fillOpacity(override: number | undefined, mapOpacity: number): number {
    return this.clamp(override ?? mapOpacity, MAP_FILL_OPACITY.max, MAP_FILL_OPACITY.min, MAP_FILL_OPACITY.default);
  }

  private clamp(value: number | undefined, max: number, min: number, fallback: number): number {
    if (value === undefined || !Number.isFinite(value)) return fallback;

    return Math.min(max, Math.max(min, Math.round(value)));
  }
}
