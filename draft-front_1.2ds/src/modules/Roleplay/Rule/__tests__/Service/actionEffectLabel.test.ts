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
        type: 'current_action_attack_target_characteristic_modifier',
        check_code: 'melee-combat',
        characteristic_code: 'perception',
        delta: -3,
        min: 0,
        scope: { components: ['strike'], hit_count: 1 },
      }),
    ).toBe('-3 к Ближнему бою от Восприятия(вплоть до 0 от Восприятия) у цели для удара текущего (действие)');
    expect(
      actionEffectLabelService.describe({
        type: 'current_action_attack_dodge_soak',
        size_delta: -3,
        ignore_at_sr: 3,
        scope: { components: ['strike'], hit_count: 1 },
      }),
    ).toContain('смягчение уклона');
    expect(
      actionEffectLabelService.describe({
        type: 'current_action_check_modifier',
        check_codes: ['check-hit'],
        delta: -1,
        source_code: 'action',
      }),
    ).toBe('1 помехи к текущим проверкам на попадание (действие)');
    expect(
      actionEffectLabelService.describe({
        type: 'optional_after_strike_check',
        check_code: 'check-willpower',
        difficulty: 3,
        skip_parent_pending: true,
        self_damage: { size_delta: -1, damage_type_code: 'blunt', internal: true },
      }),
    ).toContain('после удара проверка Волю против 3');
    expect(
      actionEffectLabelService.describe({
        type: 'current_action_durability_shave',
        short_extra_on_first_one: true,
        scope: { components: ['strike'], hit_count: 1 },
        damage_type_codes: ['cutting', 'slashing', 'piercing'],
      }),
    ).toContain('режущего урона, рубящего урона, колющего урона');
    expect(
      actionEffectLabelService.describe({
        type: 'last_strike_snapshot',
        kind: 'other',
        hits: [
          { targetKey: 'character:1', attackSr: 5 },
          { targetKey: 'character:2', attackSr: 0 },
        ],
      }),
    ).toBe('прошлый удар: попал, РУ 5; промах');
  });
});
