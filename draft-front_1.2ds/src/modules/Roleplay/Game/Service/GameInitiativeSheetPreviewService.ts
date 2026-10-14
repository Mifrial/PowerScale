import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { InitiativeCharacteristicView } from '@/modules/Roleplay/Game/Utils/initiativeCharacteristic';

/**
 * Значения характеристик из листа game.getSheets, без runtime-проекции.
 */
export class GameInitiativeSheetPreviewService {
  /** Закупки листа → вид для селекта характеристики. */
  viewsOf(sheet: Record<string, unknown>, rules: Rule[]): Map<string, InitiativeCharacteristicView> {
    const rows = sheet.characteristicPurchases;
    const views = new Map<string, InitiativeCharacteristicView>();
    if (!Array.isArray(rows)) {
      return views;
    }

    for (const row of rows) {
      if (!row || typeof row !== 'object') continue;
      const record = row as Record<string, unknown>;
      const code = record.characteristicCode;
      const value = record.value;
      if (typeof code !== 'string' || code === '' || !value || typeof value !== 'object') continue;
      const pair = value as Record<string, unknown>;
      if (typeof pair.base !== 'number' || typeof pair.size !== 'number') continue;
      const rule = rules.find((candidate) => candidate.code === code);
      views.set(code, {
        code,
        name: rule?.name ?? code,
        value: { base: pair.base, size: pair.size },
      });
    }

    return views;
  }
}
