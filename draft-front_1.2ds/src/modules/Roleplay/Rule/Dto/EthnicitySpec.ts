import type { EthnicityRole } from '@/modules/Roleplay/Rule/Enum/EthnicityRole';
import type { EthnicityUsage } from '@/modules/Roleplay/Rule/Dto/EthnicityUsage';

/** Спека народности: сток-кластер или выбираемый народ, дерево через parent_code. */
export interface EthnicitySpec {
  type: 'ethnicity';
  role: EthnicityRole;
  parent_code: string | null;
  race_codes: string[];
  language_codes: string[];
  usages: EthnicityUsage[];
}
