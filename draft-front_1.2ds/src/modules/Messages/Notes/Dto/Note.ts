export interface Note {
  id: number;
  hostType: string;
  hostId: number;
  authorId: number;
  authorName: string;
  authorLogin: string;
  title: string;
  bodyHtml: string;
  bodyText: string;
  pinned: boolean;
  sortOrder: number;
  active: boolean;
  canEdit: boolean;
  canDelete: boolean;
  createdAt: number;
  updatedAt: number;
}
