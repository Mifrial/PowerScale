import { onUnmounted, reactive, shallowRef, watch, type Ref } from 'vue';
import type { MapAreaEdit } from '@/modules/Roleplay/Map/Dto/MapAreaEdit';
import type { MapDraftApi } from '@/modules/Roleplay/Map/Composables/useMapDraft';
import type { MapHandle } from '@/modules/Roleplay/Map/Dto/MapHandle';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapRecognitionOptions } from '@/modules/Roleplay/Map/Dto/MapRecognitionOptions';
import type { MapRecognitionRegion } from '@/modules/Roleplay/Map/Dto/MapRecognitionRegion';
import type { MapRecognitionResult } from '@/modules/Roleplay/Map/Dto/MapRecognitionResult';
import type { MapSize } from '@/modules/Roleplay/Map/Dto/MapSize';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import type { MapViewport } from '@/modules/Roleplay/Map/Dto/MapViewport';
import { MAP_RECOGNITION } from '@/modules/Roleplay/Map/Constant/Map/MAP_RECOGNITION';
import { getFileApi } from '@/modules/Core/File/init';
import { mapGeometryService, mapRecognitionService } from '@/modules/Roleplay/Map/init';
import { parseHex } from '@/modules/Roleplay/Map/Utils/color';

const EMPTY_STATS = {
  found: 0,
  objects: 0,
  skippedSmall: 0,
  skippedLarge: 0,
  skippedThin: 0,
  meanVertices: 0,
  maxVertices: 0,
  included: 0,
};

/**
 * Состояние распознавания объектов по подложке.
 *
 * Держит загруженную копию подложки, образцы цвета, настройки поиска и превью.
 * Сами объекты создаются в черновике карты одной транзакцией.
 */
export function useMapRecognition(params: {
  variant: Ref<MapVariant | null>;
  viewport: Ref<MapViewport>;
  canvasSize: Ref<MapSize>;
  worldSize: Ref<MapSize>;
  draft: MapDraftApi;
}) {
  const settings = reactive({
    tolerance: MAP_RECOGNITION.defaults.tolerance,
    ignoreLuminance: MAP_RECOGNITION.defaults.ignoreLuminance,
    minArea: MAP_RECOGNITION.defaults.minArea,
    maxArea: MAP_RECOGNITION.defaults.maxArea,
    gap: MAP_RECOGNITION.defaults.gap,
    simplify: MAP_RECOGNITION.defaults.simplify,
    maxVertices: MAP_RECOGNITION.defaults.maxVertices,
    smoothArcs: MAP_RECOGNITION.defaults.smoothArcs,
    onlyVisible: false,
  });
  const state = reactive({
    imageState: 'idle' as 'idle' | 'loading' | 'ready' | 'error',
    isBusy: false,
    isPicking: false,
    error: null as string | null,
    hasResult: false,
    stats: { ...EMPTY_STATS },
  });
  const samples = reactive<string[]>([]);
  const result = shallowRef<MapRecognitionResult | null>(null);
  const excluded = shallowRef<boolean[]>([]);
  const previewMask = shallowRef<HTMLCanvasElement | null>(null);
  const previewPaths = shallowRef<MapPoint[][]>([]);
  const previewExcluded = shallowRef<boolean[]>([]);

  let sourceData: ImageData | null = null;
  let sourceWidth = 0;
  let sourceHeight = 0;
  let loadToken = 0;

  async function load(): Promise<void> {
    const publicId = params.variant.value?.baseFile?.publicId;
    loadToken += 1;
    const token = loadToken;
    sourceData = null;
    sourceWidth = 0;
    sourceHeight = 0;
    clearPreview();
    state.error = null;
    if (!publicId) {
      state.imageState = 'idle';

      return;
    }

    state.imageState = 'loading';
    try {
      const image = await loadImage(getFileApi().getViewUrl(publicId));
      if (token !== loadToken) return;
      const maxSide = MAP_RECOGNITION.limits.maxImageSide;
      const ratio = Math.min(1, maxSide / Math.max(image.naturalWidth, image.naturalHeight));
      sourceWidth = Math.max(1, Math.round(image.naturalWidth * ratio));
      sourceHeight = Math.max(1, Math.round(image.naturalHeight * ratio));
      const canvas = document.createElement('canvas');
      canvas.width = sourceWidth;
      canvas.height = sourceHeight;
      const context = canvas.getContext('2d');
      if (!context) throw new Error('Не удалось подготовить подложку');
      context.drawImage(image, 0, 0, sourceWidth, sourceHeight);
      sourceData = context.getImageData(0, 0, sourceWidth, sourceHeight);
      state.imageState = 'ready';
    } catch (caught) {
      if (token !== loadToken) return;
      state.imageState = 'error';
      state.error = caught instanceof Error ? caught.message : 'Не удалось загрузить подложку';
    }
  }

  function updateSettings(patch: {
    tolerance?: number;
    ignoreLuminance?: boolean;
    minArea?: number;
    maxArea?: number;
    gap?: number;
    simplify?: number;
    maxVertices?: number;
    smoothArcs?: boolean;
    onlyVisible?: boolean;
  }): void {
    Object.assign(settings, patch);
  }

  function startPick(): void {
    if (state.imageState !== 'ready') {
      state.error = 'Подложка ещё не загружена';
      state.isPicking = false;

      return;
    }
    state.isPicking = true;
    state.error = null;
  }

  function sampleAt(point: MapPoint): boolean {
    const pixel = toSource(point);
    if (!pixel || !sourceData) return false;
    const index = (pixel[1] * sourceWidth + pixel[0]) * 4;
    const color = hexOf(sourceData.data[index], sourceData.data[index + 1], sourceData.data[index + 2]);
    if (!samples.includes(color)) {
      if (samples.length >= MAP_RECOGNITION.limits.maxSamples) {
        state.error = `Не больше ${MAP_RECOGNITION.limits.maxSamples} образцов`;

        return false;
      }
      samples.push(color);
    }
    state.isPicking = false;
    state.error = null;

    return true;
  }

  function removeSample(index: number): void {
    if (index < 0 || index >= samples.length) return;
    samples.splice(index, 1);
  }

  function clearSamples(): void {
    samples.length = 0;
  }

  async function detect(): Promise<void> {
    if (state.imageState !== 'ready' || !sourceData) {
      state.error = 'Подложка ещё не загружена';

      return;
    }
    if (samples.length === 0) {
      state.error = 'Возьмите хотя бы один образец цвета';

      return;
    }

    state.isBusy = true;
    state.error = null;
    // Даём браузеру нарисовать индикатор до синхронного прохода по пикселям.
    await new Promise((resolve) => setTimeout(resolve, 0));
    try {
      const options: MapRecognitionOptions = {
        samples: [...samples],
        tolerance: settings.tolerance,
        ignoreLuminance: settings.ignoreLuminance,
        minArea: settings.minArea,
        maxArea: settings.maxArea,
        gap: settings.gap,
        simplify: settings.simplify,
        maxVertices: settings.maxVertices,
        smoothArcs: settings.smoothArcs,
        region: visibleRegion(),
      };
      const found = mapRecognitionService.detect(
        { width: sourceWidth, height: sourceHeight, data: sourceData.data },
        options,
      );
      result.value = found;
      excluded.value = new Array<boolean>(found.objects.length).fill(false);
      previewPaths.value = found.objects.map((object) => {
        const points = object.points.map((point) => toWorld(point));
        const handles = object.handles.map((handle) => toWorldHandle(handle));

        return mapGeometryService.flattenContour(points, handles);
      });
      previewExcluded.value = excluded.value.slice();
      previewMask.value = maskCanvas(found.mask);
      state.hasResult = true;
      state.stats = {
        found: found.found,
        objects: found.objects.length,
        skippedSmall: found.skippedSmall,
        skippedLarge: found.skippedLarge,
        skippedThin: found.skippedThin,
        meanVertices: found.meanVertices,
        maxVertices: found.maxVertices,
        included: found.objects.length,
      };
    } catch (caught) {
      state.hasResult = false;
      state.error = caught instanceof Error ? caught.message : 'Не удалось распознать объекты';
    } finally {
      state.isBusy = false;
    }
  }

  /** Клик по пятну включает или исключает его. */
  function toggleAt(point: MapPoint): boolean {
    const objects = result.value?.objects;
    if (!objects || objects.length === 0) return false;
    // Превью рисует только контур, поэтому клик часто попадает на саму линию.
    // Допуск в экранных px делает попадание предсказуемым и на мелком масштабе.
    const tolerance = MAP_RECOGNITION.preview.hitRadius / Math.max(0.0001, params.viewport.value.scale);
    for (let index = objects.length - 1; index >= 0; index -= 1) {
      const path = previewPaths.value[index];
      if (!path || path.length < 3) continue;
      if (!mapGeometryService.pointInPolygon(point, path) && distanceToLoop(point, path) > tolerance) continue;
      const next = excluded.value.slice();
      next[index] = !next[index];
      excluded.value = next;
      previewExcluded.value = next;
      state.stats = {
        ...state.stats,
        included: next.filter((value) => !value).length,
      };

      return true;
    }

    return false;
  }

  /** Исключает или возвращает все найденные пятна разом. */
  function setAllExcluded(value: boolean): void {
    const objects = result.value?.objects;
    if (!objects || objects.length === 0) return;
    const next = new Array<boolean>(objects.length).fill(value);
    excluded.value = next;
    previewExcluded.value = next.slice();
    state.stats = {
      ...state.stats,
      included: value ? 0 : objects.length,
    };
  }

  function distanceToLoop(point: MapPoint, path: MapPoint[]): number {
    let nearest = mapGeometryService.distanceToPolyline(point, path);
    const last = path[path.length - 1];
    const first = path[0];
    nearest = Math.min(nearest, mapGeometryService.distanceToSegment(point, last, first));

    return nearest;
  }

  /** Создаёт включённые пятна объектами и группирует их. */
  function createObjects(edit: MapAreaEdit, groupLabel: string): number {
    const objects = result.value?.objects ?? [];
    const variantId = params.variant.value?.id;
    if (!variantId || objects.length === 0) return 0;

    const included = objects
      .map((object, index) => ({ object, index }))
      .filter((item) => !excluded.value[item.index]);
    if (included.length === 0) return 0;

    params.draft.beginHistory();
    try {
      const groupId = groupLabel.trim() === '' ? null : params.draft.addGroup(groupLabel);
      params.draft.addAreas(
        included.map((item) => ({
          edit: { ...edit, groupId, place: null },
          path: item.object.points.map((point) => toWorldRounded(point)),
          handles: item.object.handles.map((handle) => toWorldHandle(handle)),
        })),
        variantId,
      );
    } finally {
      params.draft.endHistory();
    }
    const created = included.length;
    clearPreview();

    return created;
  }

  function clearPreview(): void {
    result.value = null;
    excluded.value = [];
    previewMask.value = null;
    previewPaths.value = [];
    previewExcluded.value = [];
    state.hasResult = false;
    state.stats = { ...EMPTY_STATS };
  }

  function reset(): void {
    clearPreview();
    samples.length = 0;
    state.isPicking = false;
    state.error = null;
    Object.assign(settings, MAP_RECOGNITION.defaults, { onlyVisible: false });
  }

  function visibleRegion(): MapRecognitionRegion | null {
    if (!settings.onlyVisible || sourceWidth === 0 || sourceHeight === 0) return null;
    const viewport = params.viewport.value;
    const canvas = params.canvasSize.value;
    const world = params.worldSize.value;
    if (viewport.scale <= 0 || world.width <= 0 || world.height <= 0) return null;

    return {
      x: ((-viewport.offsetX / viewport.scale) * sourceWidth) / world.width,
      y: ((-viewport.offsetY / viewport.scale) * sourceHeight) / world.height,
      width: ((canvas.width / viewport.scale) * sourceWidth) / world.width,
      height: ((canvas.height / viewport.scale) * sourceHeight) / world.height,
    };
  }

  function toSource(point: MapPoint): [number, number] | null {
    if (sourceWidth === 0 || sourceHeight === 0) return null;
    const world = params.worldSize.value;
    if (world.width <= 0 || world.height <= 0) return null;
    const x = Math.floor((point[0] * sourceWidth) / world.width);
    const y = Math.floor((point[1] * sourceHeight) / world.height);
    if (x < 0 || y < 0 || x >= sourceWidth || y >= sourceHeight) return null;

    return [x, y];
  }

  function toWorld(point: MapPoint): MapPoint {
    const world = params.worldSize.value;

    return [(point[0] * world.width) / sourceWidth, (point[1] * world.height) / sourceHeight];
  }

  function toWorldHandle(handle: MapHandle): MapHandle {
    const world = params.worldSize.value;
    const scaleX = sourceWidth === 0 ? 0 : world.width / sourceWidth;
    const scaleY = sourceHeight === 0 ? 0 : world.height / sourceHeight;

    return {
      in: [handle.in[0] * scaleX, handle.in[1] * scaleY],
      out: [handle.out[0] * scaleX, handle.out[1] * scaleY],
    };
  }

  function toWorldRounded(point: MapPoint): MapPoint {
    const world = toWorld(point);

    return [Math.round(world[0]), Math.round(world[1])];
  }

  function maskCanvas(mask: Uint8Array): HTMLCanvasElement | null {
    if (sourceWidth === 0 || sourceHeight === 0) return null;
    const canvas = document.createElement('canvas');
    canvas.width = sourceWidth;
    canvas.height = sourceHeight;
    const context = canvas.getContext('2d');
    if (!context) return null;
    const image = context.createImageData(sourceWidth, sourceHeight);
    const [red, green, blue] = parseHex(MAP_RECOGNITION.preview.maskColor) ?? [229, 57, 53];
    for (let index = 0; index < mask.length; index += 1) {
      if (mask[index] === 0) continue;
      const offset = index * 4;
      image.data[offset] = red;
      image.data[offset + 1] = green;
      image.data[offset + 2] = blue;
      image.data[offset + 3] = 255;
    }
    context.putImageData(image, 0, 0);

    return canvas;
  }

  watch(
    () => params.variant.value?.baseFile?.publicId ?? null,
    () => {
      void load();
    },
    { immediate: true },
  );

  onUnmounted(() => {
    loadToken += 1;
  });

  return {
    settings,
    updateSettings,
    state,
    samples,
    previewMask,
    previewPaths,
    previewExcluded,
    load,
    startPick,
    sampleAt,
    removeSample,
    clearSamples,
    detect,
    toggleAt,
    setAllExcluded,
    createObjects,
    clearPreview,
    reset,
  };
}

function loadImage(url: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = () => reject(new Error('Не удалось загрузить подложку'));
    image.src = url;
  });
}

function hexOf(red: number, green: number, blue: number): string {
  const channel = (value: number): string => Math.max(0, Math.min(255, Math.round(value))).toString(16).padStart(2, '0');

  return `#${channel(red)}${channel(green)}${channel(blue)}`;
}
