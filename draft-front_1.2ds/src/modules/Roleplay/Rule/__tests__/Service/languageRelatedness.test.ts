import { describe, it, expect } from 'vitest';
import { mockLanguages } from '@/modules/Roleplay/Rule/Mock/mockLanguages';
import { languageRelatednessService } from '@/modules/Roleplay/Rule/Service/Instance/languageRelatednessService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

function extra(code: string, parent: string, role: 'stock' | 'language' = 'language'): Rule {
  return {
    id: null,
    code,
    type: 'language',
    name: code,
    description: '',
    spaceId: 1,
    spec: { type: 'language', role, parent_code: parent, script_codes: [] },
    createdAt: 1,
  };
}

describe('LanguageRelatednessService', () => {
  const rules = mockLanguages;

  it('стоки не входят в изучаемые языки', () => {
    const options = languageRelatednessService.speakableOptions(rules);
    expect(options.some((option) => option.code === 'rados')).toBe(true);
    expect(options.some((option) => option.code === 'pra-rados')).toBe(true);
    expect(options.some((option) => option.code === 'somnaril')).toBe(true);
    expect(options.some((option) => option.code === 'locx')).toBe(false);
    expect(options.some((option) => option.code === 'ishkhet')).toBe(false);
  });

  it('праязык родственен живому потомку', () => {
    expect(languageRelatednessService.kinship('pra-rados', 'rados', rules)).toBe('related');
    expect(languageRelatednessService.effectiveSpoken([{ domainCode: 'rados', level: 2 }], 'pra-rados', rules)).toBe(1);
    expect(languageRelatednessService.kinship('somnaril', 'somnoril', rules)).toBe('related');
  });

  it('новый язык долины родственен без правок существующих карт', () => {
    const withNew = [...rules, extra('new-til', 'ishkhet')];
    expect(languageRelatednessService.kinship('ulay-til', 'new-til', withNew)).toBe('related');
    expect(languageRelatednessService.kinship('ulay-til', 'new-til', rules)).toBe('none');
  });

  it('Радос 2 даёт долине 0, Радос 3 — 1', () => {
    const owned = [{ domainCode: 'rados', level: 2 }];
    expect(languageRelatednessService.effectiveSpoken(owned, 'ulay-til', rules)).toBe(0);
    expect(languageRelatednessService.effectiveSpoken([{ domainCode: 'rados', level: 3 }], 'ulay-til', rules)).toBe(1);
  });

  it('языки долины родственны друг другу', () => {
    expect(languageRelatednessService.effectiveSpoken([{ domainCode: 'ulay-til', level: 2 }], 'shyim-til', rules)).toBe(
      1,
    );
    expect(languageRelatednessService.effectiveSpoken([{ domainCode: 'ulay-til', level: 2 }], 'rados', rules)).toBe(0);
  });

  it('ярын родственен хасу, не гаргату', () => {
    expect(languageRelatednessService.effectiveSpoken([{ domainCode: 'yaryn', level: 2 }], 'khas', rules)).toBe(1);
    expect(languageRelatednessService.effectiveSpoken([{ domainCode: 'yaryn', level: 2 }], 'gargat', rules)).toBe(0);
  });

  it('диалект языка родственен предку и сиблингам ветки', () => {
    const withDialect = [...rules, extra('ulay-east', 'ulay-til')];
    expect(languageRelatednessService.kinship('ulay-east', 'ulay-til', withDialect)).toBe('related');
    expect(languageRelatednessService.kinship('ulay-east', 'ar-til', withDialect)).toBe('related');
    expect(
      languageRelatednessService.effectiveSpoken([{ domainCode: 'ulay-til', level: 2 }], 'ulay-east', withDialect),
    ).toBe(1);
  });

  it('кастом без кода не даёт семью', () => {
    expect(languageRelatednessService.effectiveSpoken([{ domainCode: null, level: 3 }], 'rados', rules)).toBe(0);
  });

  it('изолят и его диалект родственны без стока', () => {
    const isolate: Rule[] = [
      {
        id: 1,
        code: 'wood-speech',
        type: 'language',
        name: 'wood-speech',
        description: '',
        spaceId: 1,
        spec: { type: 'language', role: 'language', parent_code: null, script_codes: [] },
        createdAt: 1,
      },
      extra('wood-dialect', 'wood-speech'),
    ];
    expect(languageRelatednessService.kinship('wood-speech', 'wood-dialect', isolate)).toBe('related');
    expect(languageRelatednessService.kinship('wood-speech', 'rados', [...isolate, ...rules])).toBe('none');
  });
});
