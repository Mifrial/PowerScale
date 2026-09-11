import { describe, expect, it } from 'vitest';
import { revisionFileService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileService';
import { revisionFileImportService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileImportService';
import { generateRevisionRules } from '@/modules/Roleplay/RuleSpace/Mock/mockSpaces';
import { mockAbilitySectionTree } from '@/modules/Roleplay/RuleSpace/Mock/mockAbilitySectionTree';
import { abilitySectionTreeService } from '@/modules/Roleplay/RuleSpace/Service/Instance/abilitySectionTreeService';
import { keywords } from '@/modules/Roleplay/Keyword/Mock/mockKeywords';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { SpaceRevision } from '@/modules/Roleplay/RuleSpace/Dto/SpaceRevision';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

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
  'discharge',
  'lightning-strike',
  'lightning-generator',
  'chain-lightning',
  'charge-accumulation',
];

const FIXED_UNIX = 1767225600;

describe('revisionFile spell slice (M4/M5)', () => {
  function sliceRules(): Rule[] {
    return SLICE_CODES.map((code) => {
      const rule = ruleCatalog.find((entry) => entry.code === code);
      if (!rule) throw new Error(`нет правила ${code}`);

      return rule;
    });
  }

  it('assemble → JSON → parse → materialize сохраняет коды и spec среза', () => {
    const slice = sliceRules();
    const revision: SpaceRevision<Rule> = {
      revision: 1,
      publishedAt: FIXED_UNIX,
      spaceCode: 'dev',
      spaceName: 'Разработка',
      sections: [],
      rules: slice,
    };
    const file = revisionFileService.assemble(revision, keywords, []);
    const json = JSON.stringify(file);
    expect(json).not.toContain('"spaceId"');
    expect(json).not.toMatch(/"id":\s*9400/);
    const parsed = revisionFileService.parse(json);
    const materialized = revisionFileService.materializeRules(parsed, 1, keywords, []);
    expect(materialized.map((rule) => rule.code).sort()).toEqual([...SLICE_CODES].sort());

    const chain = materialized.find((rule) => rule.code === 'chain-lightning');
    const spec = chain?.spec as AbilitySpec;
    expect(spec.type).toBe('skill');
    if (spec.type === 'group') return;
    expect(spec.parent_ability_code).toBe('lightning-strike');
    expect(spec.spell_upgrade?.action_point_delta).toBe(1);
    expect(spec.spell_upgrade?.chain?.same_target).toBe('via_other');

    const generator = materialized.find((rule) => rule.code === 'lightning-generator')?.spec as AbilitySpec;
    expect(generator.type).toBe('spell');
    if (generator.type !== 'spell') return;
    expect(generator.spell.power).toEqual({ base: 4, size: -1 });
    expect(generator.spell.duration).toEqual({
      type: 'sustained',
      power: { type: 'parameter', parameter_code: 'x' },
    });
  });

  it('ревизия пространства содержит срез; preview без removeMissing не теряет spec', () => {
    const revisionCodes = new Set(generateRevisionRules(1, 5).map((rule) => rule.code));
    for (const code of SLICE_CODES) {
      expect(revisionCodes.has(code)).toBe(true);
    }
    const sections = abilitySectionTreeService.normalize(mockAbilitySectionTree);
    const latest: SpaceRevision<Rule> = {
      revision: 5,
      publishedAt: FIXED_UNIX,
      spaceCode: 'razrabotka',
      spaceName: 'Разработка',
      rules: generateRevisionRules(1, 5),
      sections,
    };
    const file = revisionFileService.assemble({ ...latest, rules: sliceRules() }, keywords, []);
    const preview = revisionFileImportService.prepare(
      file,
      { keywords, mechanics: [] },
      {
        spaceId: 1,
        latest,
        removeMissing: false,
        draftRules: [],
        draftRemovedCodes: [],
        draftSections: null,
      },
    );
    expect(preview.diff.removedCodes).toEqual([]);
    const chain = preview.rules.find((rule) => rule.code === 'chain-lightning')?.spec as AbilitySpec;
    expect(chain.type).toBe('skill');
    if (chain.type === 'group') return;
    expect(chain.parent_ability_code).toBe('lightning-strike');
    const generator = preview.rules.find((rule) => rule.code === 'lightning-generator')?.spec as AbilitySpec;
    expect(generator.type).toBe('spell');
    if (generator.type !== 'spell') return;
    expect(generator.spell.power).toEqual({ base: 4, size: -1 });
    expect(generator.spell.duration).toEqual({
      type: 'sustained',
      power: { type: 'parameter', parameter_code: 'x' },
    });
  });
});
