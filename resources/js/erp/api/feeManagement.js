import client from './client';
import { prefetchAcademicsLookups } from './academics';
import { fetchStudentsForFee } from './people';

/** @type {{ data: any, at: number } | null} */
let lookupsCache = null;
/** @type {Promise<any> | null} */
let lookupsInflight = null;
/** @type {{ data: any, at: number } | null} */
let studentsLiteCache = null;
/** @type {Promise<any> | null} */
let studentsLiteInflight = null;

const TTL_MS = 40_000;

export async function fetchFeeLookups({ force = false } = {}) {
    const now = Date.now();
    if (!force && lookupsCache && now - lookupsCache.at < TTL_MS) {
        return lookupsCache.data;
    }
    if (!force && lookupsInflight) {
        return lookupsInflight;
    }

    lookupsInflight = client
        .get('/fee-management/lookups')
        .then(({ data }) => {
            lookupsCache = { data, at: Date.now() };
            return data;
        })
        .finally(() => {
            lookupsInflight = null;
        });

    return lookupsInflight;
}

export async function fetchFeeStudentsLite({ force = false } = {}) {
    const now = Date.now();
    if (!force && studentsLiteCache && now - studentsLiteCache.at < TTL_MS) {
        return studentsLiteCache.data;
    }
    if (!force && studentsLiteInflight) {
        return studentsLiteInflight;
    }

    studentsLiteInflight = fetchStudentsForFee()
        .then((data) => {
            studentsLiteCache = { data, at: Date.now() };
            return data;
        })
        .finally(() => {
            studentsLiteInflight = null;
        });

    return studentsLiteInflight;
}

/** Warm Fee Management + Academics when the sidebar group opens. */
export function prefetchFeeManagement() {
    fetchFeeLookups().catch(() => {});
    prefetchAcademicsLookups();
    // Students load on demand when Fee Receipt / Pay Fee opens the picker.
}

export function invalidateFeeLookups() {
    lookupsCache = null;
    lookupsInflight = null;
    studentsLiteCache = null;
    studentsLiteInflight = null;
}
