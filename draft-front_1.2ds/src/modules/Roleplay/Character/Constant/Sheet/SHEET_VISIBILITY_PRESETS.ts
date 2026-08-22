import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { SheetAudience } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';

/** Дефолтная видимость листа при подаче в игру — «Полностью» (участники видят все секции). */
export const SHEET_VISIBILITY_DEFAULT: SheetVisibility = [{ audience: 'all', sections: [...SHEET_VISIBLE_SECTIONS] }];

export type SheetVisibilityPresetKey = 'full' | 'brief' | 'hidden';

export interface SheetVisibilityPreset {
  key: SheetVisibilityPresetKey;
  label: string;
  value: SheetVisibility;
}

/**
 * Три частых состояния зон видимости листа: «Полностью» (все секции участникам),
 * «Имя и краткое описание», «Скрыт» (не виден игрокам; видят владелец и ведущие).
 */
export const SHEET_VISIBILITY_PRESETS: SheetVisibilityPreset[] = [
  { key: 'full', label: 'Полностью', value: SHEET_VISIBILITY_DEFAULT },
  { key: 'brief', label: 'Имя и краткое описание', value: [{ audience: 'all', sections: ['shortDescription'] }] },
  { key: 'hidden', label: 'Скрыт', value: [] },
];

function audienceKey(audience: SheetAudience): string {
  return Array.isArray(audience) ? audience.slice().sort().join(',') : audience;
}

function canonicalize(visibility: SheetVisibility): string {
  return visibility
    .map((rule) => `${audienceKey(rule.audience)}|${[...rule.sections].sort().join(',')}`)
    .sort()
    .join(';');
}

/** Какой пресет соответствует текущему набору правил (для подсветки кнопок). */
export function matchSheetVisibilityPreset(visibility: SheetVisibility): SheetVisibilityPresetKey | null {
  const current = canonicalize(visibility);
  for (const preset of SHEET_VISIBILITY_PRESETS) {
    if (canonicalize(preset.value) === current) return preset.key;
  }

  return null;
}
