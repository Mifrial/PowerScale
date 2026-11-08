import type { Note } from '@/modules/Messages/Notes/Dto/Note';

export interface NotePage {
  items: Note[];
  total: number;
}
