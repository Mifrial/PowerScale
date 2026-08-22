import type { FilterSettings } from '@/modules/Core/UI/Dto/Filter/FilterSettings';
import { FILTERBAR_STORAGE_PREFIX } from '@/modules/Core/UI/Constant/Filter/storagePrefix';

export function loadFilterSettings(settingsKey: string): FilterSettings | null {
  try {
    const raw = localStorage.getItem(`${FILTERBAR_STORAGE_PREFIX}${settingsKey}`);
    if (!raw) return null;

    return JSON.parse(raw) as FilterSettings;
  } catch {
    return null;
  }
}
