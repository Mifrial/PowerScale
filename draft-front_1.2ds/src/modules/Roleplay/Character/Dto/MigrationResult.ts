import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

/** Результат миграции персонажа на новую ревизию правил. */
export interface MigrationResult {
  kind: 'ok' | 'resolved' | 'conflicts';
  version: CharacterVersion;
  problems: {
    kind: 'removedRule' | 'raceRemoved' | 'raceBroken' | 'lostCharacteristic' | 'unmetRequirement' | 'budgetOverrun';
    label: string;
    detail: string | null;
  }[];
  diffs: {
    label: string;
    before: string;
    after: string;
    tone: 'changed' | 'green' | 'red';
    explanation?: string | null;
  }[];
  abilities: {
    label: string;
    kind: 'added' | 'removed' | 'changed';
    zone: string | null;
    costBefore: number | null;
    costAfter: number | null;
  }[];
  convertedItems: number;
}
