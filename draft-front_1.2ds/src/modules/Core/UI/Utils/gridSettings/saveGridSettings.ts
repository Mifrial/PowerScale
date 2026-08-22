import type { GridSettings } from '@/modules/Core/UI/Dto/Grid/GridSettings';
import { SMARTGRID_STORAGE_PREFIX } from '@/modules/Core/UI/Constant/Grid/storagePrefix';

export function saveGridSettings(gridId: string, settings: GridSettings) {
  try {
    localStorage.setItem(`${SMARTGRID_STORAGE_PREFIX}${gridId}`, JSON.stringify(settings));
  } catch {
    /* localStorage not available */
  }
}
