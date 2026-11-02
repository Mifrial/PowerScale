import type { ProcessStrikePackage } from '@/modules/Roleplay/Game/Dto/ProcessStrikePackage';

/** Доступное закрытие открытого процесса обычной атакой. */
export interface ProcessSessionClosing {
  cardCode: string;
  strike: ProcessStrikePackage;
}
