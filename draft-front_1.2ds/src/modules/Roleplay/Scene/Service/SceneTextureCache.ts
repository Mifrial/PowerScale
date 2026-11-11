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

  /**
   * Загружает картинку по URL и оборачивает её в текстуру Pixi.
   *
   * `Image.decode()` в некоторых окружениях не разрешается вовсе, поэтому ждём
   * событие загрузки, а таймаут оставляет декор без текстуры: сцена должна
   * открыться на маркере, а не зависнуть на ожидании картинки.
   */
  private loadImage(url: string): Promise<Texture> {
    return new Promise((resolve, reject) => {
      const image = new Image();
      const timer = window.setTimeout(() => reject(new Error(`timeout: ${url}`)), 15000);
      image.crossOrigin = 'anonymous';
      image.onload = (): void => {
        window.clearTimeout(timer);
        resolve(Texture.from(image));
      };
      image.onerror = (): void => {
        window.clearTimeout(timer);
        reject(new Error(`failed: ${url}`));
      };
      image.src = url;
      if (image.complete && image.naturalWidth > 0) {
        window.clearTimeout(timer);
        resolve(Texture.from(image));
      }
    });
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
