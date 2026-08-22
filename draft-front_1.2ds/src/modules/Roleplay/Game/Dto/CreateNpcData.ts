import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';

/** Создание НПС ведущим (status 'active') или предложение игроком (status 'proposed'). */
export interface CreateNpcData {
  name: string;
  shortDescription: string | null;
  fullDescription: string | null;
  tags: string[];
  visibility: SheetVisibility;
}
