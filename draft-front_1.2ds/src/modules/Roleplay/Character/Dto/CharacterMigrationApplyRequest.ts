import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

/** Команда применения миграции с optimistic CAS и повторяемым commandId. */
export interface CharacterMigrationApplyRequest {
  commandId: string;
  expectedActualVersion: number;
  version: CharacterVersion;
}
