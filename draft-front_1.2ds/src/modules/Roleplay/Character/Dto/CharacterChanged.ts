/** Post-commit факт изменения actual; delivery и Game context добавляет consumer. */
export interface CharacterChanged {
  characterId: number;
  actualVersion: number;
  changedSections: string[];
  actorId: number;
  mutationKind: 'character_edit' | 'runtime_effect' | 'migration';
}
