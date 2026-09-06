import client from './client';

/** @type {{ data: any, at: number } | null} */
let lookupsCache = null;
/** @type {Promise<any> | null} */
let lookupsInflight = null;

const LOOKUPS_TTL_MS = 45_000;

/**
 * Single cached payload: branches, classes (with nested sections), sections, subjects.
 * Used by filters/dropdowns — much faster than 3–4 parallel taxonomy calls.
 */
export async function fetchAcademicsLookups({ force = false } = {}) {
    const now = Date.now();
    if (!force && lookupsCache && now - lookupsCache.at < LOOKUPS_TTL_MS) {
        return lookupsCache.data;
    }
    if (!force && lookupsInflight) {
        return lookupsInflight;
    }

    lookupsInflight = client
        .get('/academics/lookups')
        .then(({ data }) => {
            lookupsCache = { data, at: Date.now() };
            return data;
        })
        .finally(() => {
            lookupsInflight = null;
        });

    return lookupsInflight;
}

/** Warm the cache as soon as the Academics sidebar group opens. */
export function prefetchAcademicsLookups() {
    fetchAcademicsLookups().catch(() => {});
}

export function invalidateAcademicsLookups() {
    lookupsCache = null;
    lookupsInflight = null;
}

/** Full class graph (sections + subjects) for Academics management pages. */
export async function fetchClassesFull() {
    const { data } = await client.get('/academics/classes', { params: { full: 1 } });
    return data;
}
