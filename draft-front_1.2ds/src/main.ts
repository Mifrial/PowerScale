import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from '@/App.vue';
import router from '@/router';
import vuetify from '@/plugins/vuetify';
import './assets/global.css';

import { registerAuthApi, registerAuthModule } from '@/modules/Core/Auth/init';
import { registerUserApi, registerGroupApi } from '@/modules/Core/User/init';
import { registerKeywordApi } from '@/modules/Roleplay/Rule/init';
import { registerTemplateApi } from '@/modules/Messages/Notifications/init';
import { registerSpaceApi } from '@/modules/Roleplay/Space/init';
import { registerRuleApi } from '@/modules/Roleplay/Rule/init';
import { registerChatApi } from '@/modules/Messages/Chat/init';
import { registerNotificationApi } from '@/modules/Messages/Notifications/init';
import { registerCsrfApi, getCsrfApi } from '@/modules/Core/Engine/init';
import { registerMacroApi, registerGameApi, registerGameModule } from '@/modules/Roleplay/Game/init';
import { registerUserModule } from '@/modules/Core/User/init';
import { registerRuleModule } from '@/modules/Roleplay/Rule/init';
import { registerSpaceModule } from '@/modules/Roleplay/Space/init';
import { registerCharacterModule, registerCharacterApi } from '@/modules/Roleplay/Character/init';
import { registerNotificationModule } from '@/modules/Messages/Notifications/init';

import { initBaseFieldTypes } from '@/modules/Core/UI/Service/Field/initBaseFieldTypes';

async function registerApiLayer(): Promise<void> {
  const isMock = import.meta.env.VITE_API_MODE !== 'real';

  if (isMock) {
    const { mockAuthApi } = await import('@/modules/Core/Auth/Mock/mockAuthApi');
    const { mockChatApi } = await import('@/modules/Messages/Chat/Mock/mockChatApi');
    const { mockNotificationApi } = await import('@/modules/Messages/Notifications/Mock/mockNotificationApi');
    const { mockCsrfApi } = await import('@/modules/Core/Engine/Mock/mockCsrfApi');
    const { mockUserApi } = await import('@/modules/Core/User/Mock/mockUserApi');
    const { mockGroupApi } = await import('@/modules/Core/User/Mock/mockGroupApi');
    const { mockKeywordApi } = await import('@/modules/Roleplay/Rule/Mock/mockKeywordApi');
    const { mockTemplateApi } = await import('@/modules/Messages/Notifications/Mock/mockTemplateApi');
    const { mockSpaceApi } = await import('@/modules/Roleplay/Space/Mock/mockSpaceApi');
    const { mockRuleApi } = await import('@/modules/Roleplay/Rule/Mock/mockRuleApi');
    const { mockMacroApi } = await import('@/modules/Roleplay/Game/Mock/mockMacroApi');
    const { mockGameApi } = await import('@/modules/Roleplay/Game/Mock/mockGameApi');
    const { mockCharacterApi } = await import('@/modules/Roleplay/Character/Mock/mockCharacterApi');

    registerAuthApi(mockAuthApi);
    registerChatApi(mockChatApi);
    registerNotificationApi(mockNotificationApi);
    registerUserApi(mockUserApi);
    registerGroupApi(mockGroupApi);
    registerKeywordApi(mockKeywordApi);
    registerTemplateApi(mockTemplateApi);
    registerSpaceApi(mockSpaceApi);
    registerRuleApi(mockRuleApi);
    registerMacroApi(mockMacroApi);
    registerGameApi(mockGameApi);
    registerCharacterApi(mockCharacterApi);
    registerCsrfApi(mockCsrfApi);
  } else {
    const { HttpClient, Engine } = await import('@/modules/Core/Engine/init');
    const { AuthApi } = await import('@/modules/Core/Auth/Service/AuthApi');
    const { ChatApi } = await import('@/modules/Messages/Chat/Service/ChatApi');
    const { NotificationApi } = await import('@/modules/Messages/Notifications/Service/NotificationApi');
    const { CsrfApi } = await import('@/modules/Core/Engine/Service/CsrfApi');
    const { UserApi } = await import('@/modules/Core/User/Service/UserApi');
    const { GroupApi } = await import('@/modules/Core/User/Service/GroupApi');
    const { KeywordApi } = await import('@/modules/Roleplay/Rule/Service/KeywordApi');
    const { NotificationTemplateApi } =
      await import('@/modules/Messages/Notifications/Service/NotificationTemplateApi');
    const { SpaceApi } = await import('@/modules/Roleplay/Space/Service/SpaceApi');
    const { RuleApi } = await import('@/modules/Roleplay/Rule/Service/RuleApi');
    const { MacroApi } = await import('@/modules/Roleplay/Game/Service/MacroApi');
    const { GameApi } = await import('@/modules/Roleplay/Game/Service/GameApi');
    const { CharacterApi } = await import('@/modules/Roleplay/Character/Service/CharacterApi');

    const csrfApi = new CsrfApi();
    registerCsrfApi(csrfApi);

    const getCsrfToken = () => getCsrfApi().getToken();
    const http = new HttpClient({ baseUrl: import.meta.env.VITE_API_BASE_URL || '/api', getCsrfToken });
    const engine = new Engine(http);
    registerAuthApi(new AuthApi(engine));
    registerChatApi(new ChatApi(engine));
    registerNotificationApi(new NotificationApi(engine));
    registerUserApi(new UserApi(engine));
    registerGroupApi(new GroupApi(engine));
    registerKeywordApi(new KeywordApi(engine));
    registerTemplateApi(new NotificationTemplateApi(engine));
    registerSpaceApi(new SpaceApi(engine));
    registerRuleApi(new RuleApi(engine));
    registerMacroApi(new MacroApi(engine));
    registerGameApi(new GameApi(engine));
    registerCharacterApi(new CharacterApi(engine));
  }
}

async function bootstrap(): Promise<void> {
  await registerApiLayer();

  getCsrfApi().initToken();

  const app = createApp(App);
  app.use(createPinia());

  registerUserModule();
  registerAuthModule();
  registerRuleModule();
  registerSpaceModule();
  registerGameModule();
  registerCharacterModule();
  registerNotificationModule();

  initBaseFieldTypes();

  app.use(router);
  app.use(vuetify);
  app.mount('#app');
}

bootstrap();
