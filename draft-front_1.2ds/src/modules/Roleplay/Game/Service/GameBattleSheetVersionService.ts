import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacteristicValue } from '@/modules/Roleplay/Character/Dto/CharacteristicValue';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterWound } from '@/modules/Roleplay/Character/Dto/CharacterWound';
import type { CharacterWoundHolder } from '@/modules/Roleplay/Character/Dto/CharacterWoundHolder';
import type { GameBattleSheet } from '@/modules/Roleplay/Game/Dto/GameBattleSheet';

/**
 * Собирает CharacterVersion из PHP sheets[] для карточки P4.
 */
export class GameBattleSheetVersionService {
  toVersion(row: GameBattleSheet): CharacterVersion {
    const choices = row.choices;
    const sheet = row.sheet;

    return {
      name: row.name,
      shortDescription: null,
      fullDescription: null,
      spaceCode: '',
      rulesRevision: row.rulesRevision ?? 0,
      raceRuleCode: this.stringOf(choices.raceCode),
      characteristics: this.characteristicsOf(sheet.characteristics ?? sheet.characteristicPurchases),
      resources: this.resourcesOf(sheet.resources),
      abilities: [],
      points: { osSpent: 0, orSpent: 0, olSpent: 0, olTotal: 0, orTotal: 0 },
      money: typeof choices.money === 'number' ? choices.money : 0,
      ageYears: null,
      inventory: this.inventoryOf(choices.inventory),
      states: this.statesOf(sheet.states),
      senses: [],
    };
  }

  private stringOf(value: unknown): string | null {
    return typeof value === 'string' ? value : null;
  }

  private characteristicsOf(raw: unknown): CharacteristicValue[] {
    return Array.isArray(raw) ? (raw as CharacteristicValue[]) : [];
  }

  private resourcesOf(raw: unknown): ResourceValue[] {
    return Array.isArray(raw) ? (raw as ResourceValue[]) : [];
  }

  private inventoryOf(raw: unknown): InventoryItem[] {
    return Array.isArray(raw) ? (raw as InventoryItem[]) : [];
  }

  private statesOf(raw: unknown): CharacterStateValue[] {
    return Array.isArray(raw) ? raw.map((row) => this.stateOf(row)) : [];
  }

  private stateOf(row: unknown): CharacterStateValue {
    const record = this.recordOf(row);
    if (!record) return { stateRuleCode: '' };
    const nested = this.recordOf(record.wound);
    const sidecar = nested ?? record;
    const wound = this.woundOf(sidecar, nested !== null);

    return {
      ...(typeof record.id === 'number' ? { id: record.id } : {}),
      stateRuleCode: typeof record.stateRuleCode === 'string' ? record.stateRuleCode : '',
      ...(typeof record.value === 'number' ? { value: record.value } : {}),
      ...(record.dimensionalValue && typeof record.dimensionalValue === 'object'
        ? { dimensionalValue: record.dimensionalValue as CharacterStateValue['dimensionalValue'] }
        : {}),
      ...(record.poison && typeof record.poison === 'object'
        ? { poison: record.poison as CharacterStateValue['poison'] }
        : {}),
      ...(typeof record.dotTurnsLeft === 'number' ? { dotTurnsLeft: record.dotTurnsLeft } : {}),
      ...(typeof record.boundSustainId === 'string' ? { boundSustainId: record.boundSustainId } : {}),
      ...(typeof record.boundSourceKey === 'string' ? { boundSourceKey: record.boundSourceKey } : {}),
      ...(record.maim && typeof record.maim === 'object' ? { maim: record.maim as CharacterStateValue['maim'] } : {}),
      ...(wound ? { wound } : {}),
    };
  }

  private woundOf(sidecar: Record<string, unknown>, nested: boolean): CharacterWound | undefined {
    const hasSidecar =
      nested ||
      'bandage' in sidecar ||
      'clotting' in sidecar ||
      'internal' in sidecar ||
      'aided' in sidecar ||
      'heldBy' in sidecar;
    if (!hasSidecar) return undefined;

    return {
      bandage: typeof sidecar.bandage === 'number' ? sidecar.bandage : 0,
      clotting: typeof sidecar.clotting === 'number' ? sidecar.clotting : 0,
      internal: sidecar.internal === true,
      aided: sidecar.aided === true,
      heldBy: this.heldByOf(sidecar.heldBy),
    };
  }

  private heldByOf(raw: unknown): CharacterWound['heldBy'] {
    if (raw === null || raw === undefined || raw === '') return null;
    if (typeof raw === 'string') return raw;
    const record = this.recordOf(raw);
    if (!record) return null;
    const kind = record.kind;
    const id = record.id;
    if ((kind === 'character' || kind === 'npc') && typeof id === 'number' && id >= 1) {
      return { kind, id } satisfies CharacterWoundHolder;
    }

    return null;
  }

  private recordOf(value: unknown): Record<string, unknown> | null {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) return null;

    return value as Record<string, unknown>;
  }
}
