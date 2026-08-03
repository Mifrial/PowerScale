import { describe, it, expect, beforeEach } from 'vitest';
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';

beforeEach(() => {
  serviceLocator.reset();
});

describe('ServiceLocator', () => {
  it('throws on get before set', () => {
    expect(() => serviceLocator.get('test')).toThrow('Service "test" not registered');
  });

  it('set + get roundtrip', () => {
    const value = { foo: 1 };
    serviceLocator.set('test', value);
    expect(serviceLocator.get<typeof value>('test')).toBe(value);
  });

  it('reset clears all', () => {
    serviceLocator.set('a', 1);
    serviceLocator.set('b', 2);
    serviceLocator.reset();
    expect(() => serviceLocator.get('a')).toThrow();
    expect(() => serviceLocator.get('b')).toThrow();
  });

  it('override existing key', () => {
    serviceLocator.set('x', 1);
    serviceLocator.set('x', 2);
    expect(serviceLocator.get<number>('x')).toBe(2);
  });

  it('generic typing works', () => {
    const impl = { bar: 'hello' };
    serviceLocator.set('foo', impl);
    const got = serviceLocator.get<typeof impl>('foo');
    expect(got.bar).toBe('hello');
  });
});
