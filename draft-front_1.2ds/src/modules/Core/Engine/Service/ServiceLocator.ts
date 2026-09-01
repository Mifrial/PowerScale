/**
 * Каталог API-сервисов модулей по строковому коду.
 */
class ServiceLocator {
  private readonly services = new Map<string, unknown>();

  /**
   * Регистрирует сервис под кодом.
   *
   * @param serviceCode Ключ регистрации.
   * @param service Экземпляр сервиса.
   */
  set<T>(serviceCode: string, service: T): void {
    this.services.set(serviceCode, service);
  }

  /**
   * Возвращает ранее зарегистрированный сервис.
   *
   * @param serviceCode Ключ регистрации.
   * @throws Если сервис не зарегистрирован.
   */
  get<T>(serviceCode: string): T {
    const service = this.services.get(serviceCode);
    if (!service) {
      throw new Error(`Service "${serviceCode}" not registered`);
    }

    return service as T;
  }

  /**
   * Снимает все регистрации (тесты и смена mock/real).
   */
  reset(): void {
    this.services.clear();
  }
}

/** Общий локатор фронтенда. */
export const serviceLocator = new ServiceLocator();
