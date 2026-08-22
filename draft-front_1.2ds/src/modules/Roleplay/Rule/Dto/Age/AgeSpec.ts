import type { Age } from '@/modules/Roleplay/Rule/Dto/Age/Age';

/** Спецификация правила «возраст» (type 'age'): набор возрастных ступеней. */
export interface AgeSpec {
  type: 'age';
  ages: Age[];
}
