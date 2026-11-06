import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapEdge } from '@/modules/Roleplay/Map/Dto/MapEdge';
import type { MapFindPageQuery } from '@/modules/Roleplay/Map/Dto/MapFindPageQuery';
import type { MapNode } from '@/modules/Roleplay/Map/Dto/MapNode';
import type { MapPage } from '@/modules/Roleplay/Map/Dto/MapPage';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapRoute } from '@/modules/Roleplay/Map/Dto/MapRoute';
import type { MapSaveResult } from '@/modules/Roleplay/Map/Dto/MapSaveResult';
import type { MapSnapshot } from '@/modules/Roleplay/Map/Dto/MapSnapshot';
import type { SaveMapData } from '@/modules/Roleplay/Map/Dto/SaveMapData';
import type { MapTerrain } from '@/modules/Roleplay/Map/Dto/MapTerrain';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import type { RouteMapData } from '@/modules/Roleplay/Map/Dto/RouteMapData';
import { MapApiError } from '@/modules/Roleplay/Map/Service/MapApiError';

const CURRENT_USER_ID = 1;

const TERRAINS: MapTerrain[] = [
  { id: 1, code: 'road', label: 'Дорога', speedFactor: 100, color: '#c5c6c6', sortOrder: 10 },
  { id: 2, code: 'path', label: 'Тропа', speedFactor: 70, color: '#b8a57a', sortOrder: 20 },
  { id: 3, code: 'grass', label: 'Трава', speedFactor: 50, color: '#9ac385', sortOrder: 30 },
  { id: 4, code: 'forest', label: 'Лес', speedFactor: 30, color: '#4e7a4e', sortOrder: 40 },
  { id: 5, code: 'swamp', label: 'Болото', speedFactor: 15, color: '#6b7050', sortOrder: 50 },
  { id: 6, code: 'water', label: 'Вода', speedFactor: 0, color: '#8fb8c9', sortOrder: 60 },
  { id: 7, code: 'wall', label: 'Стена', speedFactor: 0, color: '#5b5b5b', sortOrder: 70 },
];

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
        isPoi: true,
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
        isPoi: true,
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
        isPoi: true,
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
        isPoi: true,
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
        isPoi: true,
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
        isPoi: false,
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
        isPoi: false,
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
        layer: 'terrain',
        terrainCode: 'grass',
        kind: 'other',
        label: 'Базарная площадь',
        nodeId: 1,
        blocksMovement: false,
        shapes: [
          {
            variantId: 1,
            path: [
              [250, 270],
              [350, 270],
              [350, 370],
              [250, 370],
            ],
            isPresent: true,
          },
        ],
      },
      {
        id: 2,
        layer: 'object',
        terrainCode: null,
        kind: 'building',
        label: 'Ратуша',
        nodeId: 2,
        blocksMovement: true,
        shapes: [
          {
            variantId: 1,
            path: [
              [570, 220],
              [670, 220],
              [670, 300],
              [570, 300],
            ],
            isPresent: true,
          },
        ],
      },
      {
        id: 3,
        layer: 'object',
        terrainCode: null,
        kind: 'wall',
        label: 'Городская стена',
        nodeId: null,
        blocksMovement: true,
        shapes: [
          {
            variantId: 1,
            path: [
              [900, 200],
              [940, 200],
              [940, 800],
              [900, 800],
            ],
            isPresent: true,
          },
        ],
      },
    ],
    lines: [
      {
        id: 1,
        variantId: 1,
        terrainCode: 'water',
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
        label: 'Река',
        isPresent: true,
      },
    ],
    edges: [
      {
        id: 1,
        variantId: 1,
        fromNodeId: 1,
        toNodeId: 6,
        terrainCode: 'road',
        path: [
          [300, 320],
          [480, 380],
        ],
        distanceMeters: null,
        isDirected: false,
        label: '',
      },
      {
        id: 2,
        variantId: 1,
        fromNodeId: 6,
        toNodeId: 2,
        terrainCode: 'road',
        path: [
          [480, 380],
          [620, 260],
        ],
        distanceMeters: null,
        isDirected: false,
        label: '',
      },
      {
        id: 3,
        variantId: 1,
        fromNodeId: 2,
        toNodeId: 3,
        terrainCode: 'road',
        path: [
          [620, 260],
          [760, 360],
          [880, 520],
        ],
        distanceMeters: null,
        isDirected: false,
        label: '',
      },
      {
        id: 4,
        variantId: 1,
        fromNodeId: 1,
        toNodeId: 4,
        terrainCode: 'path',
        path: [
          [300, 320],
          [240, 620],
        ],
        distanceMeters: null,
        isDirected: false,
        label: '',
      },
      {
        id: 5,
        variantId: 1,
        fromNodeId: 4,
        toNodeId: 7,
        terrainCode: 'path',
        path: [
          [240, 620],
          [700, 600],
        ],
        distanceMeters: null,
        isDirected: false,
        label: '',
      },
      {
        id: 6,
        variantId: 1,
        fromNodeId: 6,
        toNodeId: 7,
        terrainCode: 'forest',
        path: [
          [480, 380],
          [700, 600],
        ],
        distanceMeters: null,
        isDirected: false,
        label: 'Через рощу',
      },
      {
        id: 7,
        variantId: 1,
        fromNodeId: 7,
        toNodeId: 3,
        terrainCode: 'path',
        path: [
          [700, 600],
          [880, 520],
        ],
        distanceMeters: null,
        isDirected: false,
        label: '',
      },
      {
        id: 8,
        variantId: 1,
        fromNodeId: 7,
        toNodeId: 5,
        terrainCode: 'road',
        path: [
          [700, 600],
          [1050, 700],
        ],
        distanceMeters: null,
        isDirected: false,
        label: '',
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

function terrainByCode(code: string): MapTerrain {
  const terrain = TERRAINS.find((candidate) => candidate.code === code);
  if (!terrain) {
    throw new MapApiError('MAP_INVALID', 'Неизвестный террейн');
  }

  return terrain;
}

function next(): number {
  nextId += 1;

  return nextId;
}

function touch(map: MapDetail): void {
  map.version += 1;
  map.updatedAt = nowSeconds();
  map.updatedBy = CURRENT_USER_ID;
}

function emptyVariant(snapshot: MapSnapshot['variants'][number], id: number): MapVariant {
  return {
    id,
    label: snapshot.label.trim(),
    fromMinutes: snapshot.fromMinutes,
    sortOrder: snapshot.sortOrder,
    baseFile: snapshot.filePublicId === null ? null : { publicId: snapshot.filePublicId, width: null, height: null },
    millimetersPerPixel: snapshot.millimetersPerPixel,
    isDefault: snapshot.isDefault,
  };
}

function mapVariants(map: MapDetail, snapshot: MapSnapshot): void {
  const existing = new Set(map.variants.map((variant) => variant.id));
  const variantMap = new Map<number, number>();
  map.variants = snapshot.variants.map((variant) => {
    const id = variant.id > 0 && existing.has(variant.id) ? variant.id : next();
    variantMap.set(variant.id, id);

    return emptyVariant(variant, id);
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

  map.edges = snapshot.edges.map((edge) => ({
    ...edge,
    id: edge.id > 0 ? edge.id : next(),
    variantId: variantMap.get(edge.variantId) ?? edge.variantId,
    fromNodeId: nodeMap.get(edge.fromNodeId) ?? edge.fromNodeId,
    toNodeId: nodeMap.get(edge.toNodeId) ?? edge.toNodeId,
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

export async function mockGetMapTerrains(): Promise<MapTerrain[]> {
  return structuredClone(TERRAINS);
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
      edges: [],
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

function placementOf(node: MapNode, variantId: number): MapPoint | null {
  const placement = node.placements.find((candidate) => candidate.variantId === variantId && candidate.isPresent);

  return placement ? [placement.x, placement.y] : null;
}

function edgePoints(edge: MapEdge, map: MapDetail): MapPoint[] {
  if (edge.path.length > 0) {
    return edge.path;
  }

  const from = placementOf(map.nodes.find((node) => node.id === edge.fromNodeId) ?? ({} as MapNode), edge.variantId);
  const to = placementOf(map.nodes.find((node) => node.id === edge.toNodeId) ?? ({} as MapNode), edge.variantId);

  return from && to ? [from, to] : [];
}

function edgeDistance(edge: MapEdge, map: MapDetail): number {
  if (edge.distanceMeters !== null) {
    return edge.distanceMeters;
  }

  const variant = map.variants.find((candidate) => candidate.id === edge.variantId);
  if (!variant || variant.millimetersPerPixel === null) {
    throw new MapApiError('MAP_INVALID', 'Масштаб карты не задан');
  }

  const points = edgePoints(edge, map);
  let pixels = 0;
  for (let index = 1; index < points.length; index += 1) {
    pixels += Math.hypot(points[index][0] - points[index - 1][0], points[index][1] - points[index - 1][1]);
  }

  return Math.round((pixels * variant.millimetersPerPixel) / 1000);
}

export async function mockRouteMap(data: RouteMapData): Promise<MapRoute> {
  const map = requireMap(data.mapId);
  const edges = map.edges.filter((edge) => edge.variantId === data.variantId);
  const costs = new Map<number, number>();
  const distances = new Map<number, number>();
  const previous = new Map<number, { nodeId: number; edgeId: number }>();
  costs.set(data.fromNodeId, 0);
  distances.set(data.fromNodeId, 0);
  const unvisited = new Set<number>([data.fromNodeId]);

  while (unvisited.size > 0) {
    let current: number | null = null;
    let best = Number.POSITIVE_INFINITY;
    for (const nodeId of unvisited) {
      const cost = costs.get(nodeId) ?? Number.POSITIVE_INFINITY;
      if (cost < best) {
        best = cost;
        current = nodeId;
      }
    }

    if (current === null) {
      break;
    }

    unvisited.delete(current);
    if (current === data.toNodeId) {
      break;
    }

    for (const edge of edges) {
      if (edge.fromNodeId !== current && (edge.isDirected || edge.toNodeId !== current)) {
        continue;
      }

      const terrain = terrainByCode(edge.terrainCode);
      if (terrain.speedFactor <= 0) {
        continue;
      }

      const next = edge.fromNodeId === current ? edge.toNodeId : edge.fromNodeId;
      const distance = edgeDistance(edge, map);
      const cost = (costs.get(current) ?? 0) + Math.round((distance * 100) / terrain.speedFactor);
      if (cost >= (costs.get(next) ?? Number.POSITIVE_INFINITY)) {
        continue;
      }

      costs.set(next, cost);
      distances.set(next, (distances.get(current) ?? 0) + distance);
      previous.set(next, { nodeId: current, edgeId: edge.id });
      unvisited.add(next);
    }
  }

  if (!costs.has(data.toNodeId)) {
    throw new MapApiError('MAP_NO_ROUTE', 'Между точками нет проходимого маршрута');
  }

  const nodeIds: number[] = [data.toNodeId];
  const edgeIds: number[] = [];
  let cursor = data.toNodeId;
  while (previous.has(cursor)) {
    const step = previous.get(cursor);
    if (!step) {
      break;
    }

    edgeIds.push(step.edgeId);
    cursor = step.nodeId;
    nodeIds.push(cursor);
  }

  nodeIds.reverse();
  edgeIds.reverse();
  const points: MapPoint[] = [];
  for (const edgeId of edgeIds) {
    const edge = edges.find((candidate) => candidate.id === edgeId);
    if (!edge) {
      continue;
    }

    const path = edgePoints(edge, map);
    const ordered = nodeIds.indexOf(edge.fromNodeId) <= nodeIds.indexOf(edge.toNodeId) ? path : [...path].reverse();
    for (const point of ordered) {
      const last = points[points.length - 1];
      if (!last || last[0] !== point[0] || last[1] !== point[1]) {
        points.push(point);
      }
    }
  }

  return {
    nodeIds,
    points,
    edgeIds,
    distanceMeters: distances.get(data.toNodeId) ?? 0,
    costMeters: costs.get(data.toNodeId) ?? 0,
  };
}
