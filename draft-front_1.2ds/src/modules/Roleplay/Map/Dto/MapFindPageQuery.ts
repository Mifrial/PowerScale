export interface MapFindPageQuery {
  limit: number;
  offset: number;
  q?: string;
  ownerId?: number;
  isArchived?: boolean;
}
