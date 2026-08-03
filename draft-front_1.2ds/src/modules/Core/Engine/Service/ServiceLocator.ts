class ServiceLocator {
  private readonly services = new Map<string, unknown>();

  set<T>(serviceCode: string, service: T): void {
    this.services.set(serviceCode, service);
  }

  get<T>(serviceCode: string): T {
    const service = this.services.get(serviceCode);
    if (!service) {
      throw new Error(`Service "${serviceCode}" not registered`);
    }

    return service as T;
  }

  reset(): void {
    this.services.clear();
  }
}

export const serviceLocator = new ServiceLocator();
