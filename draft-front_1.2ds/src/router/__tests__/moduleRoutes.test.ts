import { describe, it, expect } from 'vitest';
import type { RouteRecordRaw } from 'vue-router';
import { moduleChildren } from '@/router/moduleRoutes';

const meta = (r: RouteRecordRaw) => r.meta;

describe('moduleRoutes: единая 404/403', () => {
  it('содержит канонический /404 (NotFound) с guestAllowed', () => {
    const route = moduleChildren.find((r) => r.name === 'NotFound');
    expect(route?.path).toBe('/404');
    expect(meta(route as RouteRecordRaw)?.guestAllowed).toBe(true);
  });

  it('содержит catch-all /:pathMatch(.*)* с guestAllowed и редиректом на /404', () => {
    const route = moduleChildren.find((r) => r.name === 'NotFoundCatchAll');
    expect(route?.path).toBe('/:pathMatch(.*)*');
    expect(meta(route as RouteRecordRaw)?.guestAllowed).toBe(true);
    expect(route?.redirect).toBe('/404');
  });
});
