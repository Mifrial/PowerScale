import { MockGameCharacterRuntimeMutationPort } from '@/modules/Roleplay/Game/Mock/MockGameCharacterRuntimeMutationPort';
import { MockGameNpcRuntimeMutationPort } from '@/modules/Roleplay/Game/Mock/MockGameNpcRuntimeMutationPort';
import { MockGameRuntimeMutationService } from '@/modules/Roleplay/Game/Service/MockGameRuntimeMutationService';

export const mockGameRuntimeMutationService = new MockGameRuntimeMutationService(
  new MockGameCharacterRuntimeMutationPort(),
  new MockGameNpcRuntimeMutationPort(),
);
