import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';

/** Готовые параметры декора для файла ассета. */
export interface SceneDecorPreset {
  /** Масштаб в процентах от разрешения поля: видимая ширина спрайта. */
  scale: number;
  /** Радиус основания в процентах видимой ширины спрайта. */
  baseRadius: number;
  /** Радиус силуэта в процентах видимой ширины спрайта. */
  sightRadius: number;
  /** Низ силуэта в процентах видимой ширины спрайта. */
  sightBottom: number;
}

/**
 * Пресеты декора по имени загруженного файла.
 *
 * Потребитель ассета (Scene) задаёт смысл картинки: у дерева ход перекрывает
 * только ствол, у куста — почти весь спрайт. Пока это небольшая таблица, а не
 * отдельная библиотека шаблонов: новые ассеты без пресета работают на общих
 * умолчаниях.
 */
export const SCENE_DECOR_PRESETS: Readonly<Record<string, SceneDecorPreset>> = {
  'tree.png': { scale: 320, baseRadius: 10, sightRadius: 45, sightBottom: 85 },
  'bush.png': { scale: 100, baseRadius: 30, sightRadius: 40, sightBottom: 0 },
  'boulder.png': { scale: 100, baseRadius: 35, sightRadius: 45, sightBottom: 0 },
  // Старый камень: только для уже загруженных ассетов, в набор не входит.
  'rock.png': { scale: 100, baseRadius: 35, sightRadius: 45, sightBottom: 0 },
};

/** Пресет по имени файла; `null`, если файл незнаком. */
export function sceneDecorPreset(originalName: string | null | undefined): SceneDecorPreset | null {
  if (originalName === null || originalName === undefined || originalName === '') {
    return null;
  }

  const base = originalName.split(/[\\/]/).pop() ?? originalName;
  const key = base.trim().toLowerCase();

  return SCENE_DECOR_PRESETS[key] ?? null;
}

/** Свойства элемента из пресета. */
export function sceneDecorPresetProps(preset: SceneDecorPreset): SceneElementProps {
  return {
    scale: preset.scale,
    baseRadius: preset.baseRadius,
    sightRadius: preset.sightRadius,
    sightBottom: preset.sightBottom,
  };
}
