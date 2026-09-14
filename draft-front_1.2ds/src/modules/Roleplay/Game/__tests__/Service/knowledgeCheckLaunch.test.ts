import { describe, expect, it } from 'vitest';
import { knowledgeCheckLaunchService } from '@/modules/Roleplay/Game/Service/Instance/knowledgeCheckLaunchService';
import { CHECK_KNOWLEDGE_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { LAW_DEFENSE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/LAW_DEFENSE_ABILITY_CODE';
import { KNOWLEDGE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/KNOWLEDGE_ABILITY_CODE';

describe('KnowledgeCheckLaunchService', () => {
  it('не считает простой проверкой знание; поднимает DC и пишет полноту', () => {
    expect(knowledgeCheckLaunchService.isKnowledgeCheck(CHECK_KNOWLEDGE_CODE)).toBe(true);
    expect(knowledgeCheckLaunchService.isKnowledgeCheck('check-simple')).toBe(false);
    const situation = knowledgeCheckLaunchService.composeSituation('laws', 'Раден', 2, []);
    expect(situation?.slots.region?.code).toBe('raden');
    const abilities = [
      {
        ruleCode: KNOWLEDGE_ABILITY_CODE,
        level: 1,
        fieldCode: 'laws',
        slots: { region: { code: 'raden', text: 'Раден' } },
      },
      { ruleCode: LAW_DEFENSE_ABILITY_CODE, level: 2 },
    ];
    const hint = knowledgeCheckLaunchService.hint(abilities, situation, { base: 2, size: 0 }, []);
    expect(hint).toEqual({
      level: 1,
      shortage: 1,
      raised: { base: 2, size: 1 },
      lawDefenseDelta: 2,
    });
    expect(knowledgeCheckLaunchService.lawDefenseAdvantages(abilities, situation, [])[0]?.delta).toBe(2);
    expect(knowledgeCheckLaunchService.chatText('Проверка знания', 'incomplete')).toBe(
      'Проверка знания · неполный ответ',
    );
  });

  it('сортирует типы по уровню, предлагает единственный экземпляр и «Другой»', () => {
    const abilities = [
      {
        ruleCode: KNOWLEDGE_ABILITY_CODE,
        level: 2,
        fieldCode: 'physiology',
        slots: { species: { code: 'human', text: 'Человек' } },
      },
    ];
    const fields = knowledgeCheckLaunchService.fieldSelectItems(abilities);
    expect(fields[0]).toMatchObject({ value: 'physiology', level: 2 });
    expect(fields.at(-1)?.level).toBe(0);
    expect(knowledgeCheckLaunchService.suggestSoleInstance(abilities)).toEqual({
      fieldCode: 'physiology',
      slotText: 'Человек',
    });
    const speciesRules = [
      {
        id: null,
        code: 'human',
        type: 'species' as const,
        name: 'Человек',
        description: '',
        spaceId: 1,
        createdAt: 1,
      },
    ];
    const slots = knowledgeCheckLaunchService.slotSelectItems(abilities, 'physiology', speciesRules);
    expect(slots[0]).toMatchObject({ value: 'Человек', level: 2 });
    expect(slots.at(-1)?.title).toBe('Другой');
    expect(knowledgeCheckLaunchService.slotChoiceOf('physiology', 'Гоблин', speciesRules)).toBe('Другой');
    expect(
      knowledgeCheckLaunchService.composeSituation('physiology', 'Гоблин', 1, speciesRules)?.slots.species,
    ).toEqual({
      code: null,
      text: 'Гоблин',
    });
  });
});
