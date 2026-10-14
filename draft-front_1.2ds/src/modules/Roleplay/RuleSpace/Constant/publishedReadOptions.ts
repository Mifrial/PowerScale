import type { PublishedRead } from '@/modules/Roleplay/RuleSpace/Enum/PublishedRead';

/** Подписи селекта «Права доступа» на настройках мира. */
export const PUBLISHED_READ_OPTIONS: { value: PublishedRead; title: string }[] = [
  { value: 'owner', title: 'Скрыто: владелец и ключ «видеть все миры»' },
  { value: 'all', title: 'Видно всем, включая гостей' },
];
