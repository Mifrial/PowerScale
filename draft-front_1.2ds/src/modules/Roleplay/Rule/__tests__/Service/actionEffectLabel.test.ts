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
        type: 'current_action_attack_characteristic_from_success_rating',
        floor_div: 2,
        cap: 3,
        scope: { components: ['strike'], hit_count: 1 },
        source_code: 'action',
      }),
    ).toBe('+⌊РУ/2⌋ к силе удара, вплоть до +3 (действие)');
    expect(
      actionEffectLabelService.describe(
        {
          type: 'current_action_attack_characteristic_modifier',
          delta: 3,
          scope: { components: ['strike'], hit_count: 1 },
          min_occupy_hands: 2,
          damage_type_codes: ['slashing', 'blunt'],
          source_code: 'action',
        },
        'Удвоенная мощь',
      ),
    ).toBe('+3 к силе текущего удара, если оружие в 2+ руках (рубящего урона, дробящего урона) (действие)');
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
        type: 'current_action_check_modifier',
        check_codes: ['check-hit'],
        delta: -1,
        source_code: 'multi_attack',
      }),
    ).toBe('1 помехи к текущим проверкам на попадание (множественная атака)');
    expect(
      actionEffectLabelService.describe({
        type: 'next_action_attack_accuracy',
        delta: 1,
        same_target: true,
        scope: { components: ['strike'], hit_count: 1 },
      }),
    ).toContain('точности первого удара следующей атаки по той же цели');
    expect(
      actionEffectLabelService.describe({
        type: 'action_modifier',
        trigger: 'after_strike',
        scope: { components: ['strike'], hit_count: 1 },
        check: { code: 'check-willpower', difficulty: 3 },
        effects: [
          { type: 'self_damage', size_delta: -1, damage_type_code: 'blunt', ignore_defense: true },
          { type: 'skip_after_action' },
        ],
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
