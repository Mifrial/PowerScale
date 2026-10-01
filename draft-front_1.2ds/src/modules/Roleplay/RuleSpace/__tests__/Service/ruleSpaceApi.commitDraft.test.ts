import { describe, expect, it, vi } from 'vitest';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import { ActionFailure } from '@/modules/Core/Engine/Service/ActionFailure';
import { RuleSpaceApi } from '@/modules/Roleplay/RuleSpace/Service/RuleSpaceApi';

function apiWithRun(runAction: ReturnType<typeof vi.fn>): RuleSpaceApi {
  return new RuleSpaceApi({ runAction } as unknown as Engine);
}

describe('RuleSpaceApi.commitDraft', () => {
  it('кладёт номера ревизий из корня error в ActionFailure', async () => {
    const api = apiWithRun(
      vi.fn().mockResolvedValue({
        success: false,
        data: null,
        error: {
          code: 'RULESPACE_CONFLICT',
          message: 'Ревизия устарела',
          details: {
            expectedRevision: 1,
            actualRevision: 2,
          },
        },
      }),
    );

    await expect(api.commitDraft(1, [], 1)).rejects.toMatchObject({
      name: 'ActionFailure',
      code: 'RULESPACE_CONFLICT',
      message: 'Ревизия устарела',
      details: { expectedRevision: 1, actualRevision: 2 },
    });
  });

  it('не подставляет details обычной ошибке без номеров', async () => {
    const api = apiWithRun(
      vi.fn().mockResolvedValue({
        success: false,
        data: null,
        error: { code: 'RULESPACE_INVALID', message: 'Состав недопустим' },
      }),
    );

    try {
      await api.commitDraft(1, [], 1);
      expect.fail('ожидался отказ');
    } catch (error) {
      expect(error).toBeInstanceOf(ActionFailure);
      expect((error as ActionFailure).code).toBe('RULESPACE_INVALID');
      expect((error as ActionFailure).details).toBeUndefined();
    }
  });
});
