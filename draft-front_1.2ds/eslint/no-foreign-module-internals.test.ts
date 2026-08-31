import { RuleTester } from 'eslint';
import tsParser from '@typescript-eslint/parser';
import rule from './no-foreign-module-internals.js';

const filename = (rel: string) => `/workspace/src/${rel}`;

const tester = new RuleTester({
  languageOptions: {
    parser: tsParser,
    ecmaVersion: 2022,
    sourceType: 'module',
  },
});

tester.run('powerscale/no-foreign-module-internals', rule, {
  valid: [
    {
      filename: filename('modules/Roleplay/Game/Service/Foo.ts'),
      code: `import { x } from '@/modules/Roleplay/Rule/init';`,
    },
    {
      filename: filename('modules/Roleplay/Game/Service/Foo.ts'),
      code: `import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';`,
    },
    {
      filename: filename('modules/Roleplay/Space/Mock/mockSpaces.ts'),
      code: `import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';`,
    },
    {
      filename: filename('modules/Roleplay/Game/Page/GamesPage.vue'),
      code: `import { Engine } from '@/modules/Core/Engine/Service/Engine';`,
    },
    {
      filename: filename('modules/Roleplay/Game/Service/Foo.ts'),
      code: `import { GameApi } from '@/modules/Roleplay/Game/Service/GameApi';`,
    },
    {
      filename: filename('shell/AppShell.vue'),
      code: `import { useChatStore } from '@/modules/Messages/Chat/Store/chat';`,
    },
    {
      filename: filename('modules/Roleplay/routes.ts'),
      code: `import { routes as characterRoutes } from '@/modules/Roleplay/Character/routes';`,
    },
  ],
  invalid: [
    {
      filename: filename('modules/Roleplay/Game/Service/Foo.ts'),
      code: `import { x } from '@/modules/Roleplay/Character/Service/Instance/x';`,
      errors: [{ messageId: 'foreignInternals' }],
    },
    {
      filename: filename('modules/Roleplay/Character/Service/Foo.ts'),
      code: `import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';`,
      errors: [{ messageId: 'foreignInternals' }],
    },
  ],
});
