import type { NamedOption } from '@/modules/Roleplay/Rule/Dto/NamedOption';

/** Разделы каталога Развития (`spec.section` и legacy-коды признаков). */
export const ABILITY_SECTIONS: NamedOption[] = [
  { code: 'core-rules', name: 'Основные правила' },
  { code: 'method-perception', name: 'Восприятие' },
  { code: 'method-intellect', name: 'Интеллект' },
  { code: 'method-communication', name: 'Общение' },
  { code: 'section-medicine', name: 'Медицина' },
  { code: 'section-willpower', name: 'Сила воли' },
  { code: 'section-body', name: 'Тело' },
  { code: 'section-general', name: 'Общие' },
  { code: 'section-social', name: 'Социальные' },
  { code: 'section-melee', name: 'Ближний бой' },
  { code: 'section-maneuvers', name: 'Манёвры' },
  { code: 'section-ranged', name: 'Дальний бой' },
  { code: 'weapon-skill', name: 'Навыки оружия' },
  { code: 'shield-skill', name: 'Навыки щитов' },
];
