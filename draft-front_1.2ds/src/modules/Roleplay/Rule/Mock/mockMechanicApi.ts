import type { IMechanicApi } from '@/modules/Roleplay/Rule/Interface/IMechanicApi';
import { fetchMechanics } from '@/modules/Roleplay/Rule/Mock/mockMechanics';

export const mockMechanicApi: IMechanicApi = {
  getMechanics: fetchMechanics,
};
