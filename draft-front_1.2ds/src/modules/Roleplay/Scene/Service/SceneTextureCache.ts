import { Texture } from 'pixi.js';
import { getFileApi } from '@/modules/Core/File/init';

/**
 * Кэш текстур подложек и декора по `publicId`.
 *
 * Отсутствующая или битая текстура кэшируется как `null`, чтобы не грузить
 * её повторно на каждый кадр.
 */
export class SceneTextureCache {
  private readonly textures = new Map<string, Texture | null>();

  /** Догружает отсутствующие текстуры. */
  async load(publicIds: string[]): Promise<void> {
    const missing = publicIds.filter((publicId) => !this.textures.has(publicId));
    await Promise.all(
      missing.map(async (publicId) => {
        try {
          this.textures.set(publicId, await this.loadImage(getFileApi().getViewUrl(publicId)));
        } catch {
          this.textures.set(publicId, null);
        }
      }),
    );
  }

  /** Загружает картинку по URL и оборачивает её в текстуру Pixi. */
  private async loadImage(url: string): Promise<Texture> {
    const image = new Image();
    image.crossOrigin = 'anonymous';
    image.src = url;
    await image.decode();

    return Texture.from(image);
  }

  /** Текстура по внешнему id или null. */
  get(publicId: string | null): Texture | null {
    return publicId === null ? null : (this.textures.get(publicId) ?? null);
  }

  /** Освобождает текстуры. */
  destroy(): void {
    this.textures.forEach((texture) => texture?.destroy(true));
    this.textures.clear();
  }
}
