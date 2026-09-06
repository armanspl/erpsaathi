import client from './client';
import { fetchAcademicsLookups, prefetchAcademicsLookups } from './academics';

/** @type {{ data: any, at: number } | null} */
let lookupsCache = null;
/** @type {Promise<any> | null} */
let lookupsInflight = null;

const LOOKUPS_TTL_MS = 40_000;

export async function fetchAdmissionsLookups({ force = false } = {}) {
    const now = Date.now();
    if (!force && lookupsCache && now - lookupsCache.at < LOOKUPS_TTL_MS) {
        return lookupsCache.data;
    }
    if (!force && lookupsInflight) {
        return lookupsInflight;
    }

    lookupsInflight = client
        .get('/admissions/lookups')
        .then(({ data }) => {
            lookupsCache = { data, at: Date.now() };
            return data;
        })
        .finally(() => {
            lookupsInflight = null;
        });

    return lookupsInflight;
}

/** Warm Admissions + Academics taxonomy as soon as the sidebar group opens. */
export function prefetchAdmissions() {
    fetchAdmissionsLookups().catch(() => {});
    prefetchAcademicsLookups();
}

export function invalidateAdmissionsLookups() {
    lookupsCache = null;
    lookupsInflight = null;
}

export async function fetchEnquiries(params = {}) {
    const { data } = await client.get('/admissions/enquiries', { params });
    return data;
}

/** Lite student rows for Registration / Admission tables. */
export async function fetchAdmissionStudents(admissionStatus) {
    const { data } = await client.get('/people/students', {
        params: {
            lite: 1,
            admission_status: admissionStatus,
            limit: 500,
        },
    });
    return data;
}

export async function fetchStudentFull(id) {
    const { data } = await client.get(`/people/students/${id}`);
    return data;
}

export { fetchAcademicsLookups };
