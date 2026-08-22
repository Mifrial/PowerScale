import type { FilterSettings } from '@/modules/Core/UI/Dto/Filter/FilterSettings';
import { FILTERBAR_STORAGE_PREFIX } from '@/modules/Core/UI/Constant/Filter/storagePrefix';

export function saveFilterSettings(settingsKey: string, settings: FilterSettings) {
  try {
    localStorage.setItem(`${FILTERBAR_STORAGE_PREFIX}${settingsKey}`, JSON.stringify(settings));
  } catch {
    /* localStorage not available */
  }
}
