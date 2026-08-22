import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';

/** Опция выбора «от лица кого» в игровом чате (селектор в ChatInput). */
export interface ChatSpeakerOption {
  key: string;
  label: string;
  speaker: ChatSpeaker;
}
