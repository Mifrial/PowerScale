export interface CharacterDiff {
  availability: 'complete' | 'firstSubmission' | 'missingActual' | 'missingBoth';
  hasChanges: boolean;
  identity: {
    approved: {
      spaceCode: string | null;
      rulesRevision: number | null;
    };
    actual: {
      spaceCode: string | null;
      rulesRevision: number | null;
    };
    compatible: boolean;
  };
  changes: {
    path: string;
    section:
      'scalars' | 'characteristics' | 'resources' | 'abilities' | 'inventory' | 'states' | 'senses' | 'customRules';
    key: string;
    kind: 'added' | 'removed' | 'changed';
    before: unknown;
    after: unknown;
  }[];
}
