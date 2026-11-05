import type { IFileApi } from '@/modules/Core/File/Interface/IFileApi';
import type { FilePage } from '@/modules/Core/File/Dto/FilePage';
import type { FileQuery } from '@/modules/Core/File/Dto/FileQuery';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';
import { abortableDelay } from '@/modules/Core/Engine/Mock/abortableDelay';

const PLACEHOLDER_SVG =
  '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="200">' +
  '<rect width="320" height="200" fill="#455a64"/>' +
  '<text x="160" y="105" fill="#eceff1" font-family="sans-serif" font-size="20" text-anchor="middle">file</text>' +
  '</svg>';

const initialCatalog: FileRecord[] = [
  {
    id: 3,
    publicId: 'cccccccccccccccccccccccccccccccc',
    ownerId: 2,
    originalName: 'battle-map.png',
    mime: 'image/png',
    size: 1_240_000,
    width: 1920,
    height: 1080,
    sha256: 'c'.repeat(64),
    isArchived: false,
    isDerived: false,
    archivedAt: null,
    createdAt: 1_700_000_300,
  },
  {
    id: 2,
    publicId: 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
    ownerId: 1,
    originalName: 'portrait.webp',
    mime: 'image/webp',
    size: 320_000,
    width: 512,
    height: 512,
    sha256: 'b'.repeat(64),
    isArchived: false,
    isDerived: false,
    archivedAt: null,
    createdAt: 1_700_000_200,
  },
  {
    id: 1,
    publicId: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    ownerId: 1,
    originalName: 'old-avatar.png',
    mime: 'image/png',
    size: 90_000,
    width: 256,
    height: 256,
    sha256: 'a'.repeat(64),
    isArchived: true,
    isDerived: false,
    archivedAt: 1_700_000_250,
    createdAt: 1_700_000_100,
  },
];

let catalog: FileRecord[] = [...initialCatalog];
const viewUrls = new Map<string, string>();

function randomPublicId(): string {
  return Array.from({ length: 32 }, () => Math.floor(Math.random() * 16).toString(16)).join('');
}

function randomSha256(): string {
  return Array.from({ length: 64 }, () => Math.floor(Math.random() * 16).toString(16)).join('');
}

function placeholderUrl(): string {
  if (typeof URL.createObjectURL !== 'function') {
    return `data:image/svg+xml,${encodeURIComponent(PLACEHOLDER_SVG)}`;
  }

  return URL.createObjectURL(new Blob([PLACEHOLDER_SVG], { type: 'image/svg+xml' }));
}

function matches(record: FileRecord, query: FileQuery): boolean {
  if (query.mime && record.mime !== query.mime) return false;
  if (query.ownerId !== undefined && record.ownerId !== query.ownerId) return false;
  if (query.isArchived !== undefined && record.isArchived !== query.isArchived) return false;
  if (query.from !== undefined && record.createdAt < query.from) return false;
  if (query.to !== undefined && record.createdAt > query.to) return false;

  return true;
}

/** Сбрасывает мок и отзывает выданные object URL. */
export function resetMockFileApi(): void {
  catalog = [...initialCatalog];
  for (const url of viewUrls.values()) {
    if (typeof URL.revokeObjectURL === 'function') URL.revokeObjectURL(url);
  }
  viewUrls.clear();
}

export const mockFileApi: IFileApi = {
  async upload(file: File, signal?: AbortSignal): Promise<FileRecord> {
    await abortableDelay(80, signal);
    const nextId = catalog.reduce((maxId, record) => Math.max(maxId, record.id), 0) + 1;
    const publicId = randomPublicId();
    const record: FileRecord = {
      id: nextId,
      publicId,
      ownerId: 1,
      originalName: file.name || 'file',
      mime: file.type || 'application/octet-stream',
      size: file.size,
      width: null,
      height: null,
      sha256: randomSha256(),
      isArchived: false,
      isDerived: false,
      archivedAt: null,
      createdAt: Math.floor(Date.now() / 1000),
    };
    catalog = [record, ...catalog];

    return record;
  },

  async findPage(query: FileQuery, signal?: AbortSignal): Promise<FilePage> {
    await abortableDelay(80, signal);
    const matched = catalog.filter((record) => matches(record, query));
    const items = matched.slice(query.offset, query.offset + query.limit);

    return { items, total: matched.length };
  },

  async findOwnImages(limit: number, offset: number, signal?: AbortSignal): Promise<FilePage> {
    await abortableDelay(80, signal);
    const matched = catalog.filter(
      (record) => record.ownerId === 1 && !record.isArchived && !record.isDerived && record.mime.startsWith('image/'),
    );

    return { items: matched.slice(offset, offset + limit), total: matched.length };
  },

  async get(id: number, signal?: AbortSignal): Promise<FileRecord> {
    await abortableDelay(80, signal);
    const record = catalog.find((entry) => entry.id === id);
    if (!record) throw new Error('File is not found');

    return record;
  },

  getViewUrl(publicId: string): string {
    const existing = viewUrls.get(publicId);
    if (existing) return existing;
    const url = placeholderUrl();
    viewUrls.set(publicId, url);

    return url;
  },
};
