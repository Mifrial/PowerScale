import { describe, expect, it } from 'vitest';
import { actionEffectLabelService } from '@/modules/Roleplay/Rule/Service/Instance/actionEffectLabelService';

describe('ActionEffectLabelService', () => {
  it('пишет источник действия и обстоятельств', () => {
    expect(
      actionEffectLabelService.describe({
        type: 'current_action_attack_characteristic_modifier',
        delta: 2,
        scope: { components: ['strike'], hit_count: 1 },
      }),
    ).toBe('+2 к силе текущего удара (действие)');
    expect(
      actionEffectLabelService.describe({
        type: 'current_action_check_modifier',
        check_codes: ['check-hit'],
        delta: -2,
      }),
    ).toBe('2 помехи к текущим проверкам на попадание (обстоятельства)');
    expect(
      actionEffectLabelService.describe(
        {
          type: 'current_action_attack_characteristic_modifier',
          delta: 1,
          scope: { components: ['strike'], hit_count: 1 },
          min_occupy_hands: 2,
          damage_type_codes: ['slashing', 'blunt'],
        },
        'Удвоенная мощь',
      ),
    ).toBe('+1 к силе текущего удара, если оружие в 2+ руках (рубящего урона, дробящего урона) (Удвоенная мощь)');
    expect(
      actionEffectLabelService.describe({
        type: 'optional_after_strike_check',
        check_code: 'check-willpower',
        difficulty: 3,
        skip_parent_pending: true,
        self_damage: { size_delta: -1, damage_type_code: 'blunt', internal: true },
      }),
    ).toContain('после удара проверка Волю против 3');
  });
});
