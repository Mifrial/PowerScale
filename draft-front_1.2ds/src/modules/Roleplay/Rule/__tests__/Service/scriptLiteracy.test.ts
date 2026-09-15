import { describe, it, expect } from 'vitest';
import { mockLanguages } from '@/modules/Roleplay/Rule/Mock/mockLanguages';
import { mockScripts } from '@/modules/Roleplay/Rule/Mock/mockScripts';
import { scriptLiteracyService } from '@/modules/Roleplay/Rule/Service/Instance/scriptLiteracyService';
import { SCRIPT_ALPHABETIC_LADDER } from '@/modules/Roleplay/Rule/Constant/Script/SCRIPT_ALPHABETIC_LADDER';
import { SCRIPT_LOGOGRAPHIC_LADDER } from '@/modules/Roleplay/Rule/Constant/Script/SCRIPT_LOGOGRAPHIC_LADDER';

describe('ScriptLiteracyService', () => {
  const rules = [...mockLanguages, ...mockScripts];

  it('лестница: алфавит 1, иероглифы 1/1/1, свой текст как алфавит', () => {
    expect(scriptLiteracyService.ladderForDomain(rules, 'raden-alphabet')).toEqual([...SCRIPT_ALPHABETIC_LADDER]);
    expect(scriptLiteracyService.ladderForDomain(rules, 'kazar-glyphs')).toEqual([...SCRIPT_LOGOGRAPHIC_LADDER]);
    expect(scriptLiteracyService.ladderForDomain(rules, 'своё письмо')).toEqual([...SCRIPT_ALPHABETIC_LADDER]);
  });

  it('раденский алфавит покрывает радос, не ярын', () => {
    const index = scriptLiteracyService.languageScriptIndex(rules);
    expect(index.get('rados')?.has('raden-alphabet')).toBe(true);
    expect(index.get('rados')?.has('raden-glyphs')).toBe(true);
    expect(index.get('yaryn')?.has('kazar-glyphs')).toBe(true);
    expect(index.get('yaryn')?.has('raden-alphabet')).toBe(false);
    expect(index.get('locx')).toBeUndefined();
  });
});
