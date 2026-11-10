import { Texture } from 'pixi.js';
import grassUrl from '@/modules/Roleplay/Scene/Asset/Terrain/grass.jpg';
import sandUrl from '@/modules/Roleplay/Scene/Asset/Terrain/sand.jpg';
import dirtUrl from '@/modules/Roleplay/Scene/Asset/Terrain/dirt.jpg';
import stoneUrl from '@/modules/Roleplay/Scene/Asset/Terrain/stone.jpg';
import mudUrl from '@/modules/Roleplay/Scene/Asset/Terrain/mud.jpg';
import bedUrl from '@/modules/Roleplay/Scene/Asset/Terrain/bed.jpg';
import wetsandUrl from '@/modules/Roleplay/Scene/Asset/Terrain/wetsand.jpg';
import waterUrl from '@/modules/Roleplay/Scene/Asset/Terrain/water_surface.jpg';
import causticsUrl from '@/modules/Roleplay/Scene/Asset/Terrain/water_caustics.png';
import macroUrl from '@/modules/Roleplay/Scene/Asset/Terrain/macro.png';
import glintsUrl from '@/modules/Roleplay/Scene/Asset/Terrain/water_glints.png';

/** Материал террейна из набора CC0. */
export type SceneTerrainMaterial =
  'grass' | 'dirt' | 'stone' | 'sand' | 'mud' | 'bed' | 'wetsand' | 'water' | 'caustics' | 'macro';

/** Источники текстур; `bed` — дно водоёма, `wetsand` — мокрый берег. */
const SOURCES: Record<SceneTerrainMaterial, string> = {
  grass: grassUrl,
  dirt: dirtUrl,
  stone: stoneUrl,
  sand: sandUrl,
  mud: mudUrl,
  bed: bedUrl,
  wetsand: wetsandUrl,
  water: waterUrl,
  caustics: causticsUrl,
  glints: glintsUrl,
  macro: macroUrl,
};

/**
 * Размер тайла материала в ипари.
 *
 * Пол и вода живут в разных масштабах: трава читается крупнее, рябь воды мельче.
 */
const PERIOD_IPARI: Record<SceneTerrainMaterial, number> = {
  grass: 2,
  dirt: 1.6,
  stone: 1.6,
  sand: 1.6,
  mud: 1.6,
  bed: 1.5,
  wetsand: 1.6,
  water: 2.4,
  caustics: 1.7,
  glints: 1.2,
  macro: 7,
};

/**
 * Бесшовные текстуры террейна.
 *
 * Набор загружается один раз на рендерер: изображения декодируются до первой
 * отрисовки, иначе первый кадр успевает показать заглушку. Текстуры получают
 * `repeat` и мипмапы, поэтому при отдалении карты не мерцают и не дают сетку.
 */
export class SceneTerrainTextureService {
  private readonly textures = new Map<SceneTerrainMaterial, Texture>();

  private loading: Promise<void> | null = null;

  /** Загружает набор; повторный вызов возвращает тот же промис. */
  load(): Promise<void> {
    if (this.loading !== null) {
      return this.loading;
    }

    const materials = Object.keys(SOURCES) as SceneTerrainMaterial[];
    this.loading = Promise.all(materials.map((material) => this.decode(material))).then(() => undefined);

    return this.loading;
  }

  /** Готовая текстура материала или null, пока набор не загружен. */
  texture(material: SceneTerrainMaterial): Texture | null {
    return this.textures.get(material) ?? null;
  }

  /** Размер тайла материала в единицах сцены. */
  period(material: SceneTerrainMaterial, resolution: number): number {
    return Math.max(1, resolution) * PERIOD_IPARI[material];
  }

  /** Освобождает текстуры и кэш. */
  destroy(): void {
    this.textures.forEach((texture) => texture.destroy(true));
    this.textures.clear();
    this.loading = null;
  }

  private async decode(material: SceneTerrainMaterial): Promise<void> {
    try {
      const image = new Image();
      image.crossOrigin = 'anonymous';
      image.src = SOURCES[material];
      await image.decode();

      const texture = Texture.from(image);
      const style = texture.source.style;
      style.addressMode = 'repeat';
      style.mipmapFilter = 'linear';
      style.maxAnisotropy = 4;
      style.update();
      texture.source.autoGenerateMipmaps = true;
      this.textures.set(material, texture);
    } catch {
      this.textures.delete(material);
    }
  }
}
