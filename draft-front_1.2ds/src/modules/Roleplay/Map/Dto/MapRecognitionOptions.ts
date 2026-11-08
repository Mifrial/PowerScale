import type { MapRecognitionRegion } from '@/modules/Roleplay/Map/Dto/MapRecognitionRegion';

/** Настройки распознавания объектов по подложке. */
export interface MapRecognitionOptions {
  /** Образцы цвета подложки в `#rrggbb`. */
  samples: string[];
  /** Допуск цвета, 0–100. */
  tolerance: number;
  /** Сравнивать оттенок и насыщенность, не яркость: тени не мешают. */
  ignoreLuminance: boolean;
  /** Минимальная площадь пятна, px². */
  minArea: number;
  /** Максимальная площадь пятна, px²; 0 — без ограничения. */
  maxArea: number;
  /** Эрозия маски перед разметкой, px: разделяет слипшиеся пятна. */
  gap: number;
  /** Допуск упрощения контура, px. */
  simplify: number;
  /** Потолок вершин у одного контура. */
  maxVertices: number;
  /** Аппроксимировать плавные участки дугами Безье; углы остаются острыми. */
  smoothArcs: boolean;
  /** Ограничение поиска; null — вся подложка. */
  region: MapRecognitionRegion | null;
}
