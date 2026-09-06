import client from './client';
import { prefetchAcademicsLookups } from './academics';
import { fetchPeopleLookups, prefetchPeople } from './people';

/** @type {{ data: any, at: number } | null} */
let lookupsCache = null;
/** @type {Promise<any> | null} */
let lookupsInflight = null;

const TTL_MS = 40_000;

export async function fetchAttendanceLookups({ force = false } = {}) {
    const now = Date.now();
    if (!force && lookupsCache && now - lookupsCache.at < TTL_MS) {
        return lookupsCache.data;
    }
    if (!force && lookupsInflight) {
        return lookupsInflight;
    }

    lookupsInflight = client
        .get('/attendance/lookups')
        .then(({ data }) => {
            lookupsCache = { data, at: Date.now() };
            return data;
        })
        .finally(() => {
            lookupsInflight = null;
        });

    return lookupsInflight;
}

/** Warm Attendance + Academics + People (teachers/drivers) when the sidebar group opens. */
export function prefetchAttendance() {
    fetchAttendanceLookups().catch(() => {});
    prefetchAcademicsLookups();
    prefetchPeople();
}

export function invalidateAttendanceLookups() {
    lookupsCache = null;
    lookupsInflight = null;
}

export { fetchPeopleLookups };
