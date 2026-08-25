import type { InjuryOutcome } from '@/modules/Roleplay/Game/Dto/InjuryOutcome';

/** Подробности проверки на увечье для кнопки [i] в чате. */
export interface InjuryDetailsPayload {
  injury: InjuryOutcome;
  damageTypeName?: string | null;
}
