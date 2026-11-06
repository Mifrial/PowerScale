import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapFindPageQuery } from '@/modules/Roleplay/Map/Dto/MapFindPageQuery';
import type { MapPage } from '@/modules/Roleplay/Map/Dto/MapPage';
import type { MapRoute } from '@/modules/Roleplay/Map/Dto/MapRoute';
import type { MapSaveResult } from '@/modules/Roleplay/Map/Dto/MapSaveResult';
import type { MapSnapshot } from '@/modules/Roleplay/Map/Dto/MapSnapshot';
import type { SaveMapData } from '@/modules/Roleplay/Map/Dto/SaveMapData';
import type { RouteMapData } from '@/modules/Roleplay/Map/Dto/RouteMapData';
import { MapApiError } from '@/modules/Roleplay/Map/Service/MapApiError';
import { MapRouteGrid } from '@/modules/Roleplay/Map/Service/MapRouteGrid';
import { mapGeometryService } from '@/modules/Roleplay/Map/Service/Instance/mapGeometryService';

const CURRENT_USER_ID = 1;


let nextId = 100;

const nowSeconds = (): number => Math.floor(Date.now() / 1000);

const maps: MapDetail[] = [
  {
    id: 1,
    ownerId: CURRENT_USER_ID,
    name: 'Аустан',
    description: 'Город на перекрёстке трактов. Демонстрационная карта.',
    visibility: 'public',
    isArchived: false,
    version: 3,
    updatedBy: CURRENT_USER_ID,
    createdAt: nowSeconds() - 864000,
    updatedAt: nowSeconds() - 3600,
    canEdit: true,
    variants: [
      {
        id: 1,
        label: 'До войны',
        fromMinutes: null,
        sortOrder: 0,
        baseFile: { publicId: 'demo-austan', width: 1200, height: 900 },
        millimetersPerPixel: 1900,
        isDefault: true,
      },
    ],
    nodes: [
      {
        id: 1,
        name: 'Базар',
        shortInfo: 'Шумный базар в центре города.',
        publicInfo: 'Здесь торгуют всем, что привозят с трактов.',
        gmInfo: 'Под базаром есть старый подвал контрабандистов.',
        linkedMapId: null,
        icon: 'mdi-storefront-outline',
        placements: [{ variantId: 1, x: 300, y: 320, isPresent: true, labelOverride: '' }],
      },
      {
        id: 2,
        name: 'Ратуша',
        shortInfo: 'Каменное здание городского совета.',
        publicInfo: 'Совет собирается по праздникам.',
        gmInfo: 'Архив совета хранит карты старых границ.',
        linkedMapId: null,
        icon: 'mdi-bank-outline',
        placements: [{ variantId: 1, x: 620, y: 260, isPresent: true, labelOverride: '' }],
      },
      {
        id: 3,
        name: 'Северные ворота',
        shortInfo: 'Ворота на тракт к перевалу.',
        publicInfo: 'Стража проверяет всех, кто входит в город.',
        gmInfo: 'Ночью ворота закрыты, но у стражи есть цена.',
        linkedMapId: null,
        icon: 'mdi-gate',
        placements: [{ variantId: 1, x: 880, y: 520, isPresent: true, labelOverride: '' }],
      },
      {
        id: 4,
        name: 'Склады',
        shortInfo: 'Пакгаузы купеческих домов.',
        publicInfo: 'Часть складов арендует гильдия.',
        gmInfo: 'В одном из складов спрятан груз без хозяина.',
        linkedMapId: null,
        icon: 'mdi-package-variant-closed',
        placements: [{ variantId: 1, x: 240, y: 620, isPresent: true, labelOverride: '' }],
      },
      {
        id: 5,
        name: 'Южный тракт',
        shortInfo: 'Дорога к портовым деревням.',
        publicInfo: 'По тракту идут караваны.',
        gmInfo: 'Караваны часто пропадают на третьем перегоне.',
        linkedMapId: 2,
        icon: 'mdi-road-variant',
        placements: [{ variantId: 1, x: 1050, y: 700, isPresent: true, labelOverride: '' }],
      },
      {
        id: 6,
        name: '',
        shortInfo: '',
        publicInfo: '',
        gmInfo: '',
        linkedMapId: null,
        icon: '',
        placements: [{ variantId: 1, x: 480, y: 380, isPresent: true, labelOverride: '' }],
      },
      {
        id: 7,
        name: '',
        shortInfo: '',
        publicInfo: '',
        gmInfo: '',
        linkedMapId: null,
        icon: '',
        placements: [{ variantId: 1, x: 700, y: 600, isPresent: true, labelOverride: '' }],
      },
    ],
    areas: [
      {
        id: 1,
        layer: 'surface',
        color: '#c9b79c',
        speed: 8000,
        label: 'Базарная площадь',
        nodeId: 1,
        sortOrder: 0,
        shapes: [
          {
            variantId: 1,
            path: [
              [250, 270],
              [350, 270],
              [350, 370],
              [250, 370],
            ],
            handles: [],
            isPresent: true,
          },
        ],
      },
      {
        id: 2,
        layer: 'object',
        color: '#9e9e9e',
        speed: 0,
        label: 'Ратуша',
        nodeId: 2,
        sortOrder: 0,
        shapes: [
          {
            variantId: 1,
            path: [
              [570, 220],
              [670, 220],
              [670, 300],
              [570, 300],
            ],
            handles: [],
            isPresent: true,
          },
        ],
      },
      {
        id: 3,
        layer: 'object',
        color: '#616161',
        speed: 0,
        label: 'Городская стена',
        nodeId: null,
        sortOrder: 1,
        shapes: [
          {
            variantId: 1,
            path: [
              [900, 200],
              [940, 200],
              [940, 800],
              [900, 800],
            ],
            handles: [],
            isPresent: true,
          },
        ],
      },
    ],
    lines: [
      {
        id: 1,
        variantId: 1,
        color: '#3f6f8f',
        speed: 400,
        points: [
          [0, 480],
          [400, 500],
          [800, 470],
          [1200, 510],
        ],
        widths: [28, 34, 30, 24],
        handles: [
          { in: [0, 0], out: [120, 0] },
          { in: [-120, 10], out: [120, -10] },
          { in: [-120, -10], out: [120, 10] },
          { in: [-120, 0], out: [0, 0] },
        ],
        segments: [{ speed: 1000, color: '#b7a98a' }],
        label: 'Река',
        isPresent: true,
        sortOrder: 0,
      },
    ],
  },
];

function requireMap(mapId: number): MapDetail {
  const map = maps.find((candidate) => candidate.id === mapId);
  if (!map) {
    throw new MapApiError('MAP_NOT_FOUND', 'Карта не найдена');
  }

  return map;
}

function next(): number {
  nextId += 1;

  return nextId;
}

function touch(map: MapDetail): void {
  map.version += 1;
  map.updatedBy = CURRENT_USER_ID;
  map.updatedAt = nowSeconds();
}

function mapVariants(map: MapDetail, snapshot: MapSnapshot): void {
  const variantMap = new Map<number, number>();
  map.variants = snapshot.variants.map((variant) => {
    const id = variant.id > 0 ? variant.id : next();
    variantMap.set(variant.id, id);
    const existing = map.variants.find((candidate) => candidate.id === variant.id);

    return {
      id,
      label: variant.label,
      fromMinutes: variant.fromMinutes,
      sortOrder: variant.sortOrder,
      baseFile: existing?.baseFile ?? null,
      millimetersPerPixel: variant.millimetersPerPixel,
      isDefault: variant.isDefault,
    };
  });

  const nodeMap = new Map<number, number>();
  map.nodes = snapshot.nodes.map((node) => {
    const id = node.id > 0 ? node.id : next();
    nodeMap.set(node.id, id);

    return {
      ...node,
      id,
      placements: node.placements.map((placement) => ({
        ...placement,
        variantId: variantMap.get(placement.variantId) ?? placement.variantId,
      })),
    };
  });

  map.areas = snapshot.areas.map((area) => ({
    ...area,
    id: area.id > 0 ? area.id : next(),
    nodeId: area.nodeId === null ? null : (nodeMap.get(area.nodeId) ?? null),
    shapes: area.shapes.map((shape) => ({
      ...shape,
      variantId: variantMap.get(shape.variantId) ?? shape.variantId,
    })),
  }));

  map.lines = snapshot.lines.map((line) => ({
    ...line,
    id: line.id > 0 ? line.id : next(),
    variantId: variantMap.get(line.variantId) ?? line.variantId,
  }));

  map.name = snapshot.name.trim();
  map.description = snapshot.description.trim();
  map.visibility = snapshot.visibility;
}

export async function mockFindMapPage(query: MapFindPageQuery): Promise<MapPage> {
  const search = (query.q ?? '').trim().toLowerCase();
  const items = maps
    .filter((map) => map.isArchived === (query.isArchived ?? false))
    .filter((map) => (query.ownerId === undefined ? true : map.ownerId === query.ownerId))
    .filter((map) => (search === '' ? true : map.name.toLowerCase().includes(search)))
    .sort((left, right) => right.updatedAt - left.updatedAt);

  return structuredClone({
    items: items.slice(query.offset, query.offset + query.limit),
    total: items.length,
  });
}

export async function mockGetMap(id: number): Promise<MapDetail> {
  return structuredClone(requireMap(id));
}

export async function mockSaveMap(data: SaveMapData): Promise<MapSaveResult> {
  if (data.id === undefined) {
    const map: MapDetail = {
      id: next(),
      ownerId: CURRENT_USER_ID,
      name: data.map.name,
      description: data.map.description,
      visibility: data.map.visibility,
      isArchived: false,
      version: 1,
      updatedBy: CURRENT_USER_ID,
      createdAt: nowSeconds(),
      updatedAt: nowSeconds(),
      canEdit: true,
      variants: [],
      nodes: [],
      areas: [],
      lines: [],
    };
    mapVariants(map, data.map);
    maps.push(map);

    return { ...structuredClone(map), warnings: [] };
  }

  const map = requireMap(data.id);
  if (!data.force && data.expectedVersion !== map.version) {
    throw new MapApiError('MAP_VERSION_CONFLICT', 'Карту изменили с момента загрузки', {
      currentVersion: map.version,
      updatedAt: map.updatedAt,
      updatedBy: map.updatedBy,
    });
  }

  mapVariants(map, data.map);
  touch(map);

  return { ...structuredClone(map), warnings: [] };
}

export async function mockArchiveMap(id: number): Promise<void> {
  const map = requireMap(id);
  map.isArchived = true;
  touch(map);
}

export async function mockRouteMap(data: RouteMapData): Promise<MapRoute> {
  const map = requireMap(data.mapId);
  const variant = map.variants.find((candidate) => candidate.id === data.variantId);
  if (!variant) {
    throw new MapApiError('MAP_NOT_FOUND', 'Вариант карты не найден');
  }
  try {
    const field = new MapRouteGrid(mapGeometryService).build(map, variant);

    return new MapRouteGrid(mapGeometryService).findRoute(
      field,
      [data.fromX, data.fromY],
      [data.toX, data.toY],
      variant.millimetersPerPixel,
    );
  } catch {
    throw new MapApiError('MAP_NO_ROUTE', 'Между точками нет проходимого маршрута');
  }
}
