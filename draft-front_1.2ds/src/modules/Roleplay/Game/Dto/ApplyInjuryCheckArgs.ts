import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { InjuryRollInput } from '@/modules/Roleplay/Game/Dto/InjuryRollInput';

import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
export interface ApplyInjuryCheckArgs {
  input: InjuryRollInput;
  rng?: DiceRng;
  rules: Rule[];
  mechanics: Mechanic[];
  gameId: number;
  targetKey: CombatEntityKey;
  targetName: string;
  chatId: number | null;
  speaker: ChatSpeaker;
  /** Авто с атаки: сложность 0 — без чата и без состояния. */
  skipIfNoRoll?: boolean;
  /** Заголовок в чат (увечье от кровопотери). */
  chatPrefix?: string;
  targetVersion?: CharacterVersion;
  sendMessage: (
    content: string,
    attachments: ChatAttachment[],
    chatId: number,
    speaker: ChatSpeaker,
  ) => Promise<boolean>;
}
