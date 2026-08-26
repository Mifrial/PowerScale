import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ExhaustionChange } from '@/modules/Roleplay/Game/Utils/exhaustionCheckMessage';

export interface ApplyExhaustionCheckArgs {
  version: CharacterVersion;
  rng?: DiceRng;
  rules: Rule[];
  mechanics: Mechanic[];
  gameId: number;
  targetKey: CombatEntityKey;
  targetName: string;
  chatId: number | null;
  speaker: ChatSpeaker;
  change: ExhaustionChange;
  sendMessage: (
    content: string,
    attachments: ChatAttachment[],
    chatId: number,
    speaker: ChatSpeaker,
  ) => Promise<boolean>;
}
