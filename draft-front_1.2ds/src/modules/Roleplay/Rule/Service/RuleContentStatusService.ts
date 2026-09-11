import { RULE_CONTENT_STATUSES } from '@/modules/Roleplay/Rule/Constant/RULE_CONTENT_STATUSES';
import { RULE_CONTENT_STATUS_CHIP_COLORS } from '@/modules/Roleplay/Rule/Constant/RULE_CONTENT_STATUS_CHIP_COLORS';
import { RULE_CONTENT_STATUS_OPTIONS } from '@/modules/Roleplay/Rule/Constant/RULE_CONTENT_STATUS_OPTIONS';

/**
 * Подписи и рамка редакционного contentStatus.
 */
export class RuleContentStatusService {
  private readonly known = new Set<string>(RULE_CONTENT_STATUSES);

  isKnown(status: string): boolean {
    return this.known.has(status);
  }

  normalize(status: string | undefined): (typeof RULE_CONTENT_STATUSES)[number] {
    if (status === 'broken' || status === 'needs_work' || status === 'ready') {
      return status;
    }

    return 'needs_work';
  }

  label(status: string | undefined): string {
    const value = status ?? 'needs_work';

    return RULE_CONTENT_STATUS_OPTIONS.find((option) => option.value === value)?.title ?? value;
  }

  chipColor(status: string | undefined): string | undefined {
    return RULE_CONTENT_STATUS_CHIP_COLORS[this.normalize(status)];
  }

  frameModifier(status: string | undefined): string {
    return `rule-content-status--${this.normalize(status)}`;
  }
}
