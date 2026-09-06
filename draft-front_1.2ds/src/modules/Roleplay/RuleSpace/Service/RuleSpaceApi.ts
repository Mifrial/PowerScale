import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { IRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/Interface/IRuleSpaceApi';
import type { Space } from '@/modules/Roleplay/RuleSpace/Dto/Space';
import type { SpaceCreateData } from '@/modules/Roleplay/RuleSpace/Dto/SpaceCreateData';
import type { SpaceUpdateData } from '@/modules/Roleplay/RuleSpace/Dto/SpaceUpdateData';
import type { SpaceRevisionMeta } from '@/modules/Roleplay/RuleSpace/Dto/SpaceRevisionMeta';
import type { SpaceRevision } from '@/modules/Roleplay/RuleSpace/Dto/SpaceRevision';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/**
 * HTTP-клиент мира правил (`ruleSpace.*`).
 */
export class RuleSpaceApi implements IRuleSpaceApi {
  constructor(private readonly engine: Engine) {}

  async getSpaces(signal?: AbortSignal): Promise<Space[]> {
    const res = await this.engine.runAction<Space[]>('ruleSpace.getList', undefined, signal);

    return res.data ?? [];
  }

  async getSpace(id: number, signal?: AbortSignal): Promise<Space> {
    const res = await this.engine.runAction<Space>('ruleSpace.get', { id }, signal);
    if (!res.data) throw new Error('Space not found');

    return res.data;
  }

  async getSpaceByCode(code: string, signal?: AbortSignal): Promise<Space> {
    const res = await this.engine.runAction<Space>('ruleSpace.getByCode', { code }, signal);
    if (!res.data) throw new Error('Space not found');

    return res.data;
  }

  async createSpace(data: SpaceCreateData, signal?: AbortSignal): Promise<Space> {
    const res = await this.engine.runAction<Space>('ruleSpace.create', data, signal);
    if (!res.data) throw new Error('Failed to create space');

    return res.data;
  }

  async updateSpace(id: number, data: SpaceUpdateData, signal?: AbortSignal): Promise<Space> {
    const res = await this.engine.runAction<Space>('ruleSpace.update', { id, ...data }, signal);
    if (!res.data) throw new Error('Failed to update space');

    return res.data;
  }

  async deactivateSpace(id: number, signal?: AbortSignal): Promise<void> {
    await this.engine.runAction('ruleSpace.deactivate', { id }, signal);
  }

  async getRevisions(spaceId: number, signal?: AbortSignal): Promise<SpaceRevisionMeta[]> {
    const res = await this.engine.runAction<SpaceRevisionMeta[]>('ruleSpace.getRevisions', { spaceId }, signal);

    return res.data ?? [];
  }

  async getRevision(spaceId: number, revision: number, signal?: AbortSignal): Promise<SpaceRevision<Rule>> {
    const res = await this.engine.runAction<SpaceRevision<Rule>>(
      'ruleSpace.getRevision',
      { spaceId, revision },
      signal,
    );
    if (!res.data) throw new Error('Revision not found');

    return res.data;
  }

  async commitDraft(
    spaceId: number,
    rules: Rule[],
    signal?: AbortSignal,
    removedCodes?: string[],
    sections?: AbilitySection[],
  ): Promise<SpaceRevision<Rule>> {
    const res = await this.engine.runAction<SpaceRevision<Rule>>(
      'ruleSpace.commitDraft',
      {
        spaceId,
        rules: rules.map((rule) => this.commitRulePayload(rule)),
        removedCodes,
        sections,
      },
      signal,
    );
    if (!res.success || res.data === null) {
      throw new Error(res.error?.message ?? 'Не удалось опубликовать черновик');
    }

    return res.data;
  }

  /**
   * Убирает null-поля Vue-DTO: PHP принимает отсутствие ключа или массив, не JSON null.
   */
  private commitRulePayload(rule: Rule): Rule {
    const payload = { ...rule };
    if (payload.mechanicPayload == null) {
      delete payload.mechanicPayload;
    }
    if (payload.spec == null) {
      delete payload.spec;
    }

    return payload;
  }
}
