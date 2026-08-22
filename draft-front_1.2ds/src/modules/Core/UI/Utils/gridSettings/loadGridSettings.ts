import type { GridSettings } from '@/modules/Core/UI/Dto/Grid/GridSettings';
import { SMARTGRID_STORAGE_PREFIX } from '@/modules/Core/UI/Constant/Grid/storagePrefix';

export function loadGridSettings(gridId: string): GridSettings | null {
  try {
    const raw = localStorage.getItem(`${SMARTGRID_STORAGE_PREFIX}${gridId}`);
    if (!raw) return null;

    return JSON.parse(raw) as GridSettings;
  } catch {
    return null;
  }
}
