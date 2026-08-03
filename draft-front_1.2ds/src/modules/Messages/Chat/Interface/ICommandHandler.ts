import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';

export interface ParsedCommand {
  content: string;
  rolls: DiceRollSpec[];
}

export interface ICommandHandler {
  command: string;
  parse(input: string): ParsedCommand | null;
}
