import type { ITokenOption } from '@/modules/Messages/Chat/Interface/ITokenOption';

export interface ITokenSource {
  type: string;
  label: string;
  icon: string;
  search(query: string): Promise<ITokenOption[]>;
}
