export interface SpaceRevision<TRule = unknown> {
  revision: number;
  publishedAt: string;
  rules: TRule[];
}
