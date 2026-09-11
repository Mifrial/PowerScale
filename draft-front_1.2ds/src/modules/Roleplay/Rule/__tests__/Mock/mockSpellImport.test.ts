import { describe, it, expect } from 'vitest';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { mockSpellImport } from '@/modules/Roleplay/Rule/Mock/mockSpellImport';
import { keywords } from '@/modules/Roleplay/Keyword/Mock/mockKeywords';
import { ruleValidationService } from '@/modules/Roleplay/Rule/Service/Instance/ruleValidationService';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { SpellSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SpellSpec';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import type { DamageTypeSpec } from '@/modules/Roleplay/Rule/Dto/Damage/DamageTypeSpec';

const SLICE_CODES = [
  'magic-power',
  'magic-control',
  'spirituality',
  'magic-core',
  'magic-core-capacity',
  'arcanist',
  'spell-sustaining',
  'becoming-arcanist',
  'structure-substitution',
  'psionic',
  'psionic-awakening',
  'psionic-control',
  'shaman',
  'otherworldly-contact',
  'careful-magic',
  'arcane',
  'shock',
  'electrocharge',
  'core-magic-deviation',
  'discharge',
  'lightning-strike',
  'lightning-generator',
  'chain-lightning',
  'charge-accumulation',
] as const;

const byCode = new Map(ruleCatalog.map((rule) => [rule.code, rule]));

function abilitySpec(code: string): AbilitySpec | undefined {
  const rule = byCode.get(code);
  if (rule?.type !== 'ability' || !rule.spec || !('type' in rule.spec)) return undefined;

  return rule.spec as AbilitySpec;
}

function spellOf(code: string): SpellSpec | undefined {
  const spec = abilitySpec(code);
  if (!spec || spec.type !== 'spell') return undefined;

  return spec.spell;
}

describe('mockSpellImport (M4)', () => {
  it('коды среза уникальны и есть в каталоге', () => {
    const ids = mockSpellImport.map((rule) => rule.id);
    const codes = mockSpellImport.map((rule) => rule.code);
    expect(new Set(ids).size).toBe(ids.length);
    expect(new Set(codes).size).toBe(codes.length);
    for (const code of SLICE_CODES) {
      expect(byCode.get(code)?.code).toBe(code);
    }
  });

  it('ядро — innate item не в секции кристаллов', () => {
    const core = byCode.get('magic-core');
    expect(core?.type).toBe('item');
    const spec = core?.spec as ItemSpec;
    expect(spec.innate).toBe(true);
    expect(spec.category).toBe('equipment');
    expect(spec.cost_gm).toBeNull();
    expect(spec.weight).toBeNull();
    expect(core?.catalogSection).toBe('items-other');
  });

  it('срез сидит в секциях волшебства', () => {
    expect(byCode.get('magic-power')?.catalogSection).toBe('magic-rules-characteristics');
    expect(byCode.get('magic-control')?.catalogSection).toBe('magic-rules-characteristics');
    expect(byCode.get('arcane')?.catalogSection).toBe('magic-rules-damage-types');
    expect(byCode.get('shock')?.catalogSection).toBe('magic-rules-states');
    expect(byCode.get('electrocharge')?.catalogSection).toBe('magic-rules-states');
    expect(byCode.get('core-magic-deviation')?.catalogSection).toBe('magic-rules-states');
    expect(byCode.get('spirituality')?.catalogSection).toBe('magic-rules-characteristics');
    expect(byCode.get('structure-substitution')?.catalogSection).toBe('abilities-acquired-magic-paths-arcanist');
    expect(byCode.get('psionic')?.catalogSection).toBe('magic-rules-paths');
    expect(byCode.get('psionic-awakening')?.catalogSection).toBe('abilities-acquired-magic-paths-psionic');
    expect(byCode.get('psionic-control')?.catalogSection).toBe('abilities-acquired-magic-paths-psionic');
    expect(byCode.get('shaman')?.catalogSection).toBe('magic-rules-paths');
    expect(byCode.get('otherworldly-contact')?.catalogSection).toBe('abilities-acquired-magic-paths-shaman');
    expect(byCode.get('careful-magic')?.catalogSection).toBe('abilities-acquired-magic-common');
    expect(byCode.get('spell-sustaining')?.type).toBe('simple');
    expect(byCode.get('spell-sustaining')?.catalogSection).toBe('magic-rules-casting');
    expect(byCode.get('becoming-arcanist')?.catalogSection).toBe('abilities-acquired-magic-paths-arcanist');
    expect(byCode.get('discharge')?.catalogSection).toBe('abilities-acquired-magic-spells-electromancy');
    expect(byCode.get('lightning-strike')?.catalogSection).toBe('abilities-acquired-magic-spells-electromancy');
    expect(byCode.get('lightning-generator')?.catalogSection).toBe('abilities-acquired-magic-spells-electromancy');
    expect(byCode.get('chain-lightning')?.catalogSection).toBe('abilities-acquired-magic-spells-electromancy');
    expect(byCode.get('charge-accumulation')?.catalogSection).toBe('abilities-acquired-magic-spells-electromancy');
    expect(byCode.get('magic-core-capacity')?.catalogSection).toBe('abilities-acquired-magic-sources-core');
    expect(byCode.get('magic-core-capacity')?.name).toBe('Врождённое магическое ядро X');
    expect(byCode.get('magic-resistance')?.catalogSection).toBe('abilities-acquired-magic-common');
    const path = abilitySpec('becoming-arcanist');
    expect(path && path.type !== 'group' ? path.zones : null).toEqual({
      or: { kind: 'array', levels_cost: [2] },
    });
    expect(path && 'requirements' in path ? path.requirements : null).toEqual([
      {
        level: 1,
        requirements: [
          { type: 'has_ability', ability_code: 'estestvoznanie', min_level: 1 },
          { type: 'characteristic_value', characteristic_code: 'magic-power', min: { base: 3, size: -1 } },
        ],
      },
    ]);
    expect(byCode.get('becoming-arcanist')?.keywordIds).toEqual(expect.arrayContaining([13, 3, 57, 228, 229]));
    expect(byCode.get('arcanist')?.type).toBe('magic_path');
    const becoming = abilitySpec('becoming-arcanist');
    expect(becoming && 'grants' in becoming ? becoming.grants[0]?.grants : []).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ type: 'magic_path', path_code: 'arcanist' }),
        expect.objectContaining({ type: 'magic_study', scope: 'spell', max_cost: 2, path_code: 'arcanist' }),
        expect.objectContaining({ type: 'characteristic', characteristic_code: 'magic-control' }),
      ]),
    );
    const substitution = abilitySpec('structure-substitution');
    expect(substitution && 'grants' in substitution ? substitution.grants[0]?.grants : []).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ type: 'magic_study', scope: 'non_spell', max_cost: 2, path_code: 'arcanist' }),
      ]),
    );
    const awakening = abilitySpec('psionic-awakening');
    expect(awakening && 'requirements' in awakening ? awakening.requirements : null).toEqual([
      {
        level: 1,
        requirements: [
          { type: 'characteristic_value', characteristic_code: 'magic-power', min: { base: 4, size: -1 } },
        ],
      },
    ]);
    expect(awakening && 'grants' in awakening ? awakening.grants[0]?.grants : []).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          type: 'magic_study',
          scope: 'spell',
          max_cost: 1,
          path_code: 'psionic',
          max_instances: 1,
          paid_cost: 0,
        }),
      ]),
    );
    const awakeningGrants = awakening && 'grants' in awakening ? (awakening.grants[0]?.grants ?? []) : [];
    expect(awakeningGrants.some((grant) => grant.type === 'ability')).toBe(false);
    expect(awakeningGrants.some((grant) => grant.type === 'magic_path')).toBe(false);
    expect(awakeningGrants.some((grant) => grant.type === 'characteristic')).toBe(false);
    expect(byCode.get('psionic-control')?.name).toBe('Контроль Псионики');
    const control = abilitySpec('psionic-control');
    expect(control && 'requirements' in control ? control.requirements : null).toEqual([
      {
        level: 1,
        requirements: [
          { type: 'has_ability', ability_code: 'psionic-awakening', min_level: 1 },
          { type: 'characteristic_value', characteristic_code: 'willpower', min: { base: 5, size: 0 } },
        ],
      },
    ]);
    expect(control && 'grants' in control ? control.grants[0]?.grants : []).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ type: 'magic_path', path_code: 'psionic' }),
        expect.objectContaining({ type: 'characteristic', characteristic_code: 'magic-control' }),
        expect.objectContaining({ type: 'magic_study', scope: 'spell', max_cost: 1, path_code: 'psionic' }),
      ]),
    );
    expect(byCode.get('psionic')?.type).toBe('magic_path');
    expect(byCode.get('shaman')?.type).toBe('magic_path');
    expect(byCode.get('shaman')?.spec).toEqual(
      expect.objectContaining({ type: 'magic_path', includes_path_codes: ['psionic'] }),
    );
    const contact = abilitySpec('otherworldly-contact');
    expect(contact && 'grants' in contact ? contact.grants[0]?.grants : []).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ type: 'magic_path', path_code: 'shaman' }),
        expect.objectContaining({ type: 'characteristic', characteristic_code: 'spirituality' }),
      ]),
    );
    expect(contact && 'requirements' in contact ? contact.requirements : null).toEqual([
      {
        level: 1,
        requirements: [
          { type: 'has_magic_path', path_code: 'psionic' },
          { type: 'magic_path_experience', path_code: 'psionic', min: 6 },
          { type: 'characteristic_value', characteristic_code: 'willpower', min: { base: 5, size: 0 } },
          { type: 'characteristic_value', characteristic_code: 'attention', min: { base: 5, size: 0 } },
          { type: 'characteristic_value', characteristic_code: 'communication', min: { base: 5, size: 0 } },
        ],
      },
    ]);
    const careful = abilitySpec('careful-magic');
    expect(byCode.get('careful-magic')?.description.includes('Общий навык волшебства')).toBe(false);
    expect(careful && careful.type !== 'group' ? careful.spell_upgrade : null).toEqual({
      action_point_delta: 1,
      check_advantage: 1,
    });
    expect(careful && careful.type !== 'group' ? careful.domain_ref : null).toBe('magic-path');
    expect(careful && careful.type !== 'group' ? careful.multiple : null).toBe(true);
    const discharge = abilitySpec('discharge');
    expect(discharge && discharge.type !== 'group' ? discharge.zones : null).toEqual({
      or: { kind: 'array', levels_cost: [1] },
    });
  });

  it('ядро X выдаёт item и Магическую мощь', () => {
    const spec = abilitySpec('magic-core-capacity');
    expect(spec?.type).toBe('trait');
    const grants = spec && 'grants' in spec ? spec.grants[0]?.grants : [];
    expect(grants).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ type: 'item', item_code: 'magic-core' }),
        expect.objectContaining({ type: 'characteristic_parameter', characteristic_code: 'magic-power' }),
      ]),
    );
  });

  it('заклинания соответствуют контракту M3', () => {
    const discharge = abilitySpec('discharge');
    expect(discharge?.type).toBe('spell');
    if (discharge?.type !== 'spell') return;
    expect(discharge.hit_resolution).toEqual({ type: 'attack' });
    expect(discharge.spell.duration).toEqual({ type: 'instant' });
    expect(discharge.spell.control).toEqual({ base: 3, size: -1 });
    expect(discharge.spell.power).toEqual({ type: 'parameter', parameter_code: 'x' });
    expect(discharge.multiple).toBe(true);
    expect(discharge.domain_ref).toBe('magic-path');
    expect(discharge.action_components.some((component) => component.type === 'somatic')).toBe(true);
    expect(byCode.get('discharge')?.description.includes('Мощь x↑')).toBe(false);
    expect(byCode.get('discharge')?.description.includes('data-rule-code="electricity"')).toBe(true);
    expect(byCode.get('discharge')?.description.includes('множитель РУ')).toBe(false);
    expect(discharge.spell.damage).toEqual({
      damage_type_code: 'electricity',
      experience_keyword_code: 'electromancy',
      power_modify_steps: [
        { min_experience: 0, modify: 3 },
        { min_experience: 10, modify: 4 },
        { min_experience: 20, modify: 5 },
        { min_experience: 30, modify: 6 },
      ],
    });
    expect('difficulty' in discharge.spell).toBe(false);
    expect(discharge.requirements).toEqual([]);

    const strike = spellOf('lightning-strike');
    expect(strike?.control).toEqual({ base: 4, size: -1 });
    expect(strike?.damage).toEqual({
      damage_type_code: 'electricity',
      experience_keyword_code: 'electromancy',
      power_modify_steps: [
        { min_experience: 0, modify: 0 },
        { min_experience: 10, modify: 1 },
        { min_experience: 20, modify: 2 },
        { min_experience: 30, modify: 3 },
      ],
      falloff: { free_ipari: 2, size_per_extra_ipari: 1, min: { base: 3, size: -1 } },
    });
    const strikeSpec = abilitySpec('lightning-strike');
    expect(strikeSpec?.type).toBe('spell');
    if (strikeSpec?.type !== 'spell') return;
    expect(strikeSpec.hit_resolution).toEqual({ type: 'auto', rating: 2 });
    expect(strikeSpec.action_components).toEqual(
      expect.arrayContaining([{ type: 'somatic', note: 'указующая цель рука', occupy_hands: 1 }]),
    );
    expect(strikeSpec.requirements).toEqual([
      {
        level: 1,
        requirements: [{ type: 'has_ability', ability_code: 'discharge' }],
      },
    ]);
    expect(byCode.get('lightning-strike')?.keywordIds).not.toContain(2);
    expect(byCode.get('lightning-strike')?.description.includes('data-rule-code="electricity"')).toBe(true);
    expect(byCode.get('lightning-strike')?.description.includes('3↓')).toBe(true);
    expect(byCode.get('lightning-strike')?.description.includes('{3|-1}')).toBe(false);

    const generator = abilitySpec('lightning-generator');
    expect(generator?.type).toBe('spell');
    if (generator?.type !== 'spell') return;
    expect(generator.spell.power).toEqual({ base: 4, size: -1 });
    expect(generator.spell.control).toEqual({ base: 5, size: -1 });
    expect(generator.spell.duration).toEqual({
      type: 'sustained',
      power: { type: 'parameter', parameter_code: 'x' },
    });
    expect(generator.hit_resolution).toEqual({ type: 'none' });
    expect(generator.spell.charge?.default_cap).toBe(1);
    expect(byCode.get('lightning-generator')?.description.includes('Электрозаряд')).toBe(true);
    expect(byCode.get('lightning-generator')?.description.includes('заклинание с признаком электромансии')).toBe(true);
    expect(byCode.get('lightning-generator')?.description.includes('2 ОД')).toBe(true);
    expect(byCode.get('lightning-generator')?.description.includes('data-rule-code="spell-sustaining"')).toBe(true);
    expect(byCode.get('lightning-generator')?.description.includes('description-example')).toBe(false);
    expect(byCode.get('lightning-generator')?.description.includes('не является поддерживаемым')).toBe(true);
    expect(byCode.get('lightning-generator')?.description.includes('Мощь поддержания в первый ход')).toBe(false);
    expect(byCode.get('spell-sustaining')?.description.includes('В первый ход мощь поддержания')).toBe(true);

    const chain = abilitySpec('chain-lightning');
    expect(chain?.type).toBe('skill');
    if (chain?.type === 'group' || !chain) return;
    expect(chain.parent_ability_code).toBe('lightning-strike');
    expect(chain.spell_upgrade).toEqual({
      action_point_delta: 1,
      chain: {
        damage_size_per_hop: 1,
        min: { base: 3, size: -1 },
        retarget: 'from_last_hit',
        same_target: 'via_other',
      },
    });
    expect(byCode.get('chain-lightning')?.keywordIds).toEqual([13, 3, 227]);
    expect(byCode.get('chain-lightning')?.description.includes('3↓')).toBe(true);
    expect(byCode.get('chain-lightning')?.description.includes('{3|-1}')).toBe(false);
    expect('spell' in chain).toBe(false);
  });

  it('arcane влияет на сложность; электричество игнорирует защиту и вешает Шок', () => {
    const arcane = byCode.get('arcane')?.spec as DamageTypeSpec;
    expect(arcane.modifies_spell_difficulty).toBe(true);
    expect(arcane.defense_ignored).toBe(true);
    const electricity = byCode.get('electricity')?.spec as DamageTypeSpec;
    expect(electricity.defense_ignored).toBe(true);
    expect(electricity.max_success_rating).toBe(3);
    expect(electricity.attached_rule_codes).toContain('dt-exhaustion-to-shock');
    const shock = byCode.get('shock')?.spec as StateSpec;
    expect(shock.value_type).toBe('number');
    expect(shock.effects?.[0]).toMatchObject({
      type: 'characteristic_modify',
      characteristic_code: 'dexterity',
      per_unit: true,
    });
  });

  it('keyword electromancy есть; старые magic и magic-potential удалены', () => {
    expect(keywords.some((keyword) => keyword.code === 'electromancy' && keyword.id === 227)).toBe(true);
    expect(keywords.some((keyword) => keyword.code === 'magic-path' && keyword.id === 228)).toBe(true);
    expect(keywords.some((keyword) => keyword.code === 'psionic' && keyword.id === 230)).toBe(true);
    expect(keywords.some((keyword) => keyword.code === 'shaman' && keyword.id === 231)).toBe(true);
    expect(byCode.get('magic')?.type).toBeUndefined();
    expect(byCode.get('magic-potential')?.type).toBeUndefined();
    expect(byCode.get('magic-damage')?.type).toBe('damage_type');
  });

  it('validateCatalog не ругает коды среза', () => {
    const result = ruleValidationService.validateCatalog(ruleCatalog, keywords);
    for (const code of SLICE_CODES) {
      expect(ruleValidationService.blockingMessagesForRule(result, code)).toEqual([]);
    }
    expect(ruleValidationService.blockingMessagesForRule(result, 'magic-resistance')).toEqual([]);
  });
});
