import type { NoteState } from '@/modules/Messages/Notes/Enum/NoteState';

export interface NoteFilter {
  q?: string;
  hostType?: string;
  hostId?: number;
  authorId?: number;
  pinned?: boolean;
  state?: NoteState;
  limit?: number;
  offset?: number;
}
