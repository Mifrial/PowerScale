import { defineAsyncComponent } from 'vue';
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import type { IAuthApi } from '@/modules/Core/Auth/Interface/IAuthApi';
import { AUTH_PERMISSION_CATEGORY } from '@/modules/Core/Auth/Constant/Permission/AUTH_PERMISSION_CATEGORY';
import { registerPermissionCategory, registerUserEditSection } from '@/modules/Core/User/init';

export { passwordValidatorService } from '@/modules/Core/Auth/Service/Instance/passwordValidatorService';

export function registerAuthApi(api: IAuthApi): void {
  serviceLocator.set('Core.Auth.Service.AuthApi', api);
}

export function getAuthApi(): IAuthApi {
  return serviceLocator.get('Core.Auth.Service.AuthApi');
}

export function registerAuthModule(): void {
  registerPermissionCategory(AUTH_PERMISSION_CATEGORY);
  registerUserEditSection({
    id: 'password',
    component: defineAsyncComponent(() => import('@/modules/Core/Auth/Component/UserPasswordEditSection.vue')),
  });
}
