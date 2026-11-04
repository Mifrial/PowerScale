/** Плоский HTTP-контракт file.findPage. */
export interface FileQuery {
  limit: number;
  offset: number;
  mime?: string;
  ownerId?: number;
  isArchived?: boolean;
  from?: number;
  to?: number;
}
