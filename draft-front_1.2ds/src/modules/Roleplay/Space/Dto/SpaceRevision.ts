export interface SpaceRevision<TRule = unknown> {
  revision: number;
  publishedAt: string;
  spaceCode: string;
  spaceName: string;
  rules: TRule[];
}
