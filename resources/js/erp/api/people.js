import client from './client';
import { prefetchAcademicsLookups } from './academics';

/** @type {{ data: any, at: number } | null} */
let lookupsCache = null;
/** @type {Promise<any> | null} */
let lookupsInflight = null;
/** @type {{ data: any, at: number } | null} */
let parentsLiteCache = null;

const TTL_MS = 40_000;

export async function fetchPeopleLookups({ force = false } = {}) {
    const now = Date.now();
    if (!force && lookupsCache && now - lookupsCache.at < TTL_MS) {
        return lookupsCache.data;
    }
    if (!force && lookupsInflight) {
        return lookupsInflight;
    }

    lookupsInflight = client
        .get('/people/lookups')
        .then(({ data }) => {
            lookupsCache = { data, at: Date.now() };
            return data;
        })
        .finally(() => {
            lookupsInflight = null;
        });

    return lookupsInflight;
}

export async function fetchParentsLite({ force = false } = {}) {
    const now = Date.now();
    if (!force && parentsLiteCache && now - parentsLiteCache.at < TTL_MS) {
        return parentsLiteCache.data;
    }
    const { data } = await client.get('/people/parents', { params: { lite: 1 } });
    parentsLiteCache = { data, at: Date.now() };
    return data;
}

/** Warm People + Academics as soon as the sidebar group opens. */
export function prefetchPeople() {
    fetchPeopleLookups().catch(() => {});
    fetchParentsLite().catch(() => {});
    prefetchAcademicsLookups();
}

export function invalidatePeopleLookups() {
    lookupsCache = null;
    lookupsInflight = null;
    parentsLiteCache = null;
}

export async function fetchStudentsLite(params = {}) {
    const { data } = await client.get('/people/students', {
        params: { lite: 1, ...params },
    });
    return data;
}

/** Minimal Active-student list for Fee Receipt / Pay Fee pickers. */
export async function fetchStudentsForFee(params = {}) {
    const { data } = await client.get('/people/students', {
        params: { for: 'fee', limit: 2000, ...params },
    });
    return data;
}

export async function fetchStudentFull(id) {
    const { data } = await client.get(`/people/students/${id}`);
    return data;
}
