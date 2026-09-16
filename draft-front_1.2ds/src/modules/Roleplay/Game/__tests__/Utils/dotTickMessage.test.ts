import { describe, expect, it } from 'vitest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { DOT_TICK_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Dot/DOT_TICK_ATTACHMENT_TYPE';
import { buildDotTickAttachment, formatDotTickMessage } from '@/modules/Roleplay/Game/Utils/dotTickMessage';

const fire: Rule = {
  id: null,
  code: 'fire',
  type: 'damage_type',
  name: 'Огонь',
  description: '',
  spaceId: 1,
  spec: { type: 'damage_type', forms: { genitive: 'огня', dative: 'огню' }, attached_rule_codes: [] },
  keywordIds: [],
  mechanicId: null,
  createdAt: 1767225600,
};

describe('formatDotTickMessage', () => {
  it('шаблон с чипом типа и истощением', () => {
    expect(formatDotTickMessage('Гарик', 'character:1', 'fire', 3, 1, [fire])).toBe(
      '[[character:1,Гарик]] получает 3 урона от [[rule:fire,огня]], что наносит ему 1 истощение',
    );
  });

  it('без истощения не добавляет хвост', () => {
    expect(formatDotTickMessage('Гарик', 'character:1', 'fire', 0, 0, [fire])).toBe(
      '[[character:1,Гарик]] получает 0 урона от [[rule:fire,огня]]',
    );
  });

  it('вложение [i] несёт силу, сопротивление и истощение', () => {
    const attachment = buildDotTickAttachment(
      'Горение',
      { base: 3, size: 0 },
      'fire',
      {
        remainingSr: 1,
        appliedSr: 1,
        srCap: null,
        resistance: 0,
        penetration: 0,
        raw: 3,
        hpDamage: 3,
        exhaustion: 1,
        remainingHpDamage: 0,
        stun: null,
        shock: null,
        wound: null,
        knockout: false,
        cuttingWound: null,
        layers: [],
      },
      [fire],
      false,
    );
    expect(attachment.type).toBe(DOT_TICK_ATTACHMENT_TYPE);
    expect(attachment.payload).toMatchObject({ hpDamage: 3, exhaustion: 1, resistance: 0, damageTypeName: 'огонь' });
  });
});
