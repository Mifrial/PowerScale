import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { registerFileApi } from '@/modules/Core/File/init';
import { mockFileApi, resetMockFileApi } from '@/modules/Core/File/Mock/mockFileApi';
import { fileImageSource } from '@/modules/Core/File/Service/FileImageSource';

const publicId = 'a'.repeat(32);

beforeEach(() => {
  resetMockFileApi();
  registerFileApi(mockFileApi);
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('fileImageSource', () => {
  it('берёт URL из getViewUrl и не делает fetch', () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch');
    const getViewUrl = vi.spyOn(mockFileApi, 'getViewUrl');

    const source = fileImageSource(publicId, false);

    expect(getViewUrl).toHaveBeenCalledWith(publicId);
    expect(source).toBe(mockFileApi.getViewUrl(publicId));
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('для архивной строки и пустого id байты не просит', () => {
    const getViewUrl = vi.spyOn(mockFileApi, 'getViewUrl');

    expect(fileImageSource(publicId, true)).toBe('');
    expect(fileImageSource('', false)).toBe('');
    expect(getViewUrl).not.toHaveBeenCalled();
  });
});
