import { describe, expect, it } from 'vitest';
import { ruleContentStatusService } from '@/modules/Roleplay/Rule/Service/Instance/ruleContentStatusService';

describe('RuleContentStatusService', () => {
  it('неизвестный статус считает needs_work для рамки', () => {
    expect(ruleContentStatusService.normalize(undefined)).toBe('needs_work');
    expect(ruleContentStatusService.normalize('weird')).toBe('needs_work');
    expect(ruleContentStatusService.frameModifier('broken')).toBe('rule-content-status--broken');
  });

  it('подписывает известные статусы', () => {
    expect(ruleContentStatusService.label('ready')).toBe('Актуальный');
    expect(ruleContentStatusService.isKnown('broken')).toBe(true);
    expect(ruleContentStatusService.isKnown('text_only')).toBe(false);
  });
});
