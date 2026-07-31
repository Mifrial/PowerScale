import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import vuetify from './plugins/vuetify'
import './assets/global.css'

import { HttpClient, Engine } from '@/modules/Core/Engine'
import { registerAuthApi } from '@/modules/Core/Auth/init'
import { registerUserApi, registerGroupApi } from '@/modules/Core/User/init'
import { registerSourceApi } from '@/modules/Roleplay/Rule/Source/init'
import { registerTagApi } from '@/modules/Roleplay/Rule/Tag/init'
import { registerTemplateApi } from '@/modules/Messages/Notifications/init'
import { registerSpaceApi } from '@/modules/Roleplay/Space/init'
import { registerRuleApi } from '@/modules/Roleplay/Rule/init'
import { registerChatApi } from '@/modules/Messages/Chat/init'
import { registerNotificationApi } from '@/modules/Messages/Notifications/init'
import { registerCsrfApi, getCsrfApi } from '@/modules/Core/CSRF/init'
import { AuthApi } from '@/modules/Core/Auth/Service/AuthApi'
import { ChatApi } from '@/modules/Messages/Chat/Service/ChatApi'
import { NotificationApi } from '@/modules/Messages/Notifications/Service/NotificationApi'
import { CsrfApi } from '@/modules/Core/CSRF/Service/CsrfApi'
import { mockAuthApi } from '@/modules/Core/Auth/Service/mockAuthApi'
import { mockChatApi } from '@/modules/Messages/Chat/Service/mockChatApi'
import { mockNotificationApi } from '@/modules/Messages/Notifications/Service/mockNotificationApi'
import { mockCsrfApi } from '@/modules/Core/CSRF/Service/mockCsrf'
import { mockUserApi } from '@/modules/Core/User/Service/mockUserApi'
import { mockGroupApi } from '@/modules/Core/User/Service/mockGroupApi'
import { mockSourceApi } from '@/modules/Roleplay/Rule/Source/Service/mockSourceApi'
import { mockTagApi } from '@/modules/Roleplay/Rule/Tag/Service/mockTagApi'
import { mockTemplateApi } from '@/modules/Messages/Notifications/Service/mockTemplateApi'
import { mockSpaceApi } from '@/modules/Roleplay/Space/Service/mockSpaceApi'
import { mockRuleApi } from '@/modules/Roleplay/Rule/Service/mockRuleApi'
import { UserApi } from '@/modules/Core/User/Service/UserApi'
import { GroupApi } from '@/modules/Core/User/Service/GroupApi'
import { SourceApi } from '@/modules/Roleplay/Rule/Source/Service/SourceApi'
import { TagApi } from '@/modules/Roleplay/Rule/Tag/Service/TagApi'
import { NotificationTemplateApi } from '@/modules/Messages/Notifications/Service/NotificationTemplateApi'
import { SpaceApi } from '@/modules/Roleplay/Space/Service/SpaceApi'
import { RuleApi } from '@/modules/Roleplay/Rule/Service/RuleApi'

import { initBaseRenderers, registerRenderer } from '@/modules/Core/UI/Components/Grid'
import { initBaseFilterHandlers } from '@/modules/Core/UI/Components/FilterBar'
import UserCell from '@/modules/Core/User/Components/cells/UserCell.vue'
import ActiveCell from '@/modules/Core/UI/Components/Grid/cells/ActiveCell.vue'

const isMock = import.meta.env.VITE_API_MODE !== 'real'

if (isMock) {
  registerAuthApi(mockAuthApi)
  registerChatApi(mockChatApi)
  registerNotificationApi(mockNotificationApi)
  registerUserApi(mockUserApi)
  registerGroupApi(mockGroupApi)
  registerSourceApi(mockSourceApi)
  registerTagApi(mockTagApi)
  registerTemplateApi(mockTemplateApi)
  registerSpaceApi(mockSpaceApi)
  registerRuleApi(mockRuleApi)
  registerCsrfApi(mockCsrfApi)
} else {
  const csrfApi = new CsrfApi()
  registerCsrfApi(csrfApi)

  const getCsrfToken = () => getCsrfApi().getToken()
  const http = new HttpClient({ baseUrl: import.meta.env.VITE_API_BASE_URL || '/api', getCsrfToken })
  const engine = new Engine(http)
  registerAuthApi(new AuthApi(engine))
  registerChatApi(new ChatApi(engine))
  registerNotificationApi(new NotificationApi(engine))
  registerUserApi(new UserApi(engine))
  registerGroupApi(new GroupApi(engine))
  registerSourceApi(new SourceApi(engine))
  registerTagApi(new TagApi(engine))
  registerTemplateApi(new NotificationTemplateApi(engine))
  registerSpaceApi(new SpaceApi(engine))
  registerRuleApi(new RuleApi(engine))
}

getCsrfApi().initToken()

initBaseRenderers()
initBaseFilterHandlers()
registerRenderer('user', UserCell)
registerRenderer('active', ActiveCell)

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(vuetify)
app.mount('#app')
