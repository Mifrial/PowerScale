/**
 * «От лица кого» написано сообщение в игровом чате (ТР §8 «Чат игры»).
 * Игроки пишут только от лица одного из своих персонажей; ведущий — от роли
 * ведущего, от лица персонажа или НПС. Опционально: в обычных чатах сообщение
 * без speaker (автор = username).
 */
export type ChatSpeaker =
  | { kind: 'gm' }
  | { kind: 'character'; characterId: number; characterName: string }
  | { kind: 'npc'; npcId: number; npcName: string };
