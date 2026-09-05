export interface AbilitySection {
  code: string;
  name: string;
  parentCode: string | null;
  sortOrder: number;
  /** Корень редактора или произвольная строка с сервера. */
  catalogRootFor?: string;
}
