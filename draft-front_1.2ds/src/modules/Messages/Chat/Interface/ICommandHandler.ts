import type { ParsedCommand } from '@/modules/Messages/Chat/Dto/ParsedCommand';

export interface ICommandHandler {
  command: string;
  parse(input: string): ParsedCommand | null;
}
