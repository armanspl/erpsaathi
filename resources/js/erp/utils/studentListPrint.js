/**
 * Browser print layouts for People → Students:
 * Parents details (Guardian List), Basic details, Student list.
 */

function esc(v) {
    return String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function openPrint(title, styles, body) {
    const win = window.open('', '_blank');
    if (!win) return false;
    win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>${esc(title)}</title>
<style>
* { box-sizing: border-box; }
body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 12mm 10mm; font-size: 12px; }
${styles}
</style></head><body>${body}
<script>window.onload = function () { window.focus(); window.print(); };<\/script>
</body></html>`);
    win.document.close();
    return true;
}

function printDateLabel() {
    const d = new Date();
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return `${dd}-${mm}-${d.getFullYear()}`;
}

function schoolHeaderHtml(school) {
    const logo = school.logo_url
        ? `<img class="logo" src="${esc(school.logo_url)}" alt="" />`
        : `<div class="logo-fallback">${esc((school.school_name || 'S').slice(0, 2).toUpperCase())}</div>`;
    const lines = [];
    if (school.address) {
        lines.push(`<div class="meta-line"><span class="ico">📍</span> ${esc(school.address)}</div>`);
    }
    const contactBits = [];
    if (school.email) contactBits.push(`<span class="ico">✉</span> ${esc(school.email)}`);
    if (school.website) contactBits.push(`<span class="ico">🌐</span> ${esc(school.website)}`);
    if (school.phone) contactBits.push(`<span class="ico">☎</span> ${esc(school.phone)}`);
    if (contactBits.length) {
        lines.push(`<div class="meta-line">${contactBits.join(' &nbsp;&nbsp; ')}</div>`);
    }
    return `<div class="hdr">
        <div class="hdr-left">${logo}<div class="hdr-info">
            <div class="school-name">${esc(school.school_name || 'School')}</div>
            ${lines.join('')}
        </div></div>
        <div class="print-date">Print Date : ${printDateLabel()}</div>
    </div>`;
}

const SHARED_CSS = `
@page { size: A4 portrait; margin: 8mm; }
.hdr { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 10px; }
.hdr-left { display: flex; gap: 10px; align-items: flex-start; min-width: 0; }
.logo { width: 58px; height: 58px; object-fit: contain; flex-shrink: 0; }
.logo-fallback { width: 58px; height: 58px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; }
.school-name { font-size: 20px; font-weight: 800; letter-spacing: 0.02em; line-height: 1.15; margin-bottom: 4px; }
.meta-line { font-size: 11px; color: #333; line-height: 1.45; }
.ico { font-size: 10px; }
.print-date { border: 1px solid #cbd5e1; background: #f1f5f9; padding: 6px 10px; font-size: 11px; white-space: nowrap; flex-shrink: 0; }
.title-bar { background: #e2e8f0; text-align: center; font-weight: 800; letter-spacing: 0.08em; padding: 7px 8px; margin: 6px 0 12px; font-size: 14px; }
`;

function classSec(row) {
    const c = row.class && row.class !== '—' ? row.class : '';
    const s = row.section && row.section !== '—' ? row.section : '';
    if (c && s) return `${c} - ${s}`;
    return c || s || '';
}

function sessionLabel(sessionName) {
    if (!sessionName || sessionName === 'All' || sessionName === '__all__') return '';
    return String(sessionName).replace(/\s*-\s*/, ' - ');
}

function statusLabel(row, s) {
    return row.status || s.status || '';
}

function formatAddress(s) {
    const parts = [
        s.address || s.address_line_1,
        s.city,
        s.state,
        s.state || s.city ? 'India' : null,
        s.pincode ? `Pin - ${s.pincode}` : null,
    ].filter(Boolean);
    return parts.join(', ');
}

function guardianOfStudent(s) {
    return s.guardian || s.father || null;
}

function parentPhones(s) {
    const phones = [s.father?.phone, s.mother?.phone, s.mobile]
        .map((p) => (p || '').trim())
        .filter(Boolean);
    return [...new Set(phones)].join(', ');
}

/**
 * @param {'parents'|'basic'|'list'} kind
 * @param {Array} rows  DataTable-shaped rows with __raw
 * @param {{ school: object, sessionName: string, transportedIds?: Set }} ctx
 */
export function printStudentReport(kind, rows, ctx) {
    const school = ctx.school || {};
    const session = sessionLabel(ctx.sessionName);
    const header = schoolHeaderHtml(school);

    if (kind === 'parents') {
        const body = rows.map((row, i) => {
            const s = row.__raw || {};
            const g = guardianOfStudent(s);
            return `<tr>
                <td class="c">${i + 1}</td>
                <td>${esc(g?.name || '')}</td>
                <td>${esc(g?.email || '')}</td>
                <td>${esc(g?.phone || s.mobile || '')}</td>
                <td>${esc(`${s.admission_no || ''} - ${s.name || ''}`)}</td>
                <td>${esc(classSec(row))}</td>
                <td class="c">${esc(row.roll ?? s.roll_no ?? '')}</td>
            </tr>`;
        }).join('');
        return openPrint('Guardian List', `${SHARED_CSS}
            table { width: 100%; border-collapse: collapse; }
            th, td { border-bottom: 1px solid #cbd5e1; padding: 7px 6px; text-align: left; vertical-align: top; font-size: 11.5px; }
            th { font-weight: 700; border-bottom: 1.5px solid #94a3b8; }
            .c { text-align: center; width: 36px; }
            tr { page-break-inside: avoid; }
        `, `${header}<div class="title-bar">GUARDIAN LIST</div>
            <table>
                <thead><tr>
                    <th class="c">#</th><th>Name</th><th>Email</th><th>Contact No.</th>
                    <th>Guardian of</th><th>Class - Sec</th><th class="c">Roll</th>
                </tr></thead>
                <tbody>${body || '<tr><td colspan="7">No students.</td></tr>'}</tbody>
            </table>`);
    }

    if (kind === 'basic') {
        const blocks = rows.map((row, i) => {
            const s = row.__raw || {};
            const g = guardianOfStudent(s);
            const transport = ctx.transportedIds?.has?.(s.id) || row.transport === 'Yes';
            const admDate = s.admission_date
                ? String(s.admission_date).slice(0, 10)
                : '';
            const phones = parentPhones(s);
            const gPhone = g?.phone || s.mobile || '';
            return `<div class="block">
                <div class="r1">
                    <span class="sr">Sr. No. ${i + 1}</span>
                    <span>Adm. No. <b>${esc(s.admission_no || '')}</b></span>
                    <span>Adm. Date : <b>${esc(admDate)}</b></span>
                    <span>Class : <b>${esc(classSec(row))}</b></span>
                    <span>Roll : <b>${esc(row.roll ?? s.roll_no ?? '')}</b></span>
                    <span>Session : <b>${esc(session)}</b></span>
                </div>
                <div class="r2">
                    <span>Student Name : <b>${esc(s.name || '')}</b></span>
                    <span>Guardian Name : <b>${esc(g?.name || '')}</b></span>
                    <span class="ph">${gPhone ? `☎ ${esc(gPhone)}` : ''}</span>
                </div>
                <div class="r2">
                    <span>Father's Name : <b>${esc(s.father?.name || '')}</b></span>
                    <span>Mother's Name : <b>${esc(s.mother?.name || '')}</b></span>
                    <span class="ph">${phones ? `☎ ${esc(phones)}` : ''}</span>
                </div>
                <div class="r2">
                    <span class="addr">Address : <b>${esc(formatAddress(s))}</b></span>
                    <span class="st">Status : <b class="ok">${esc(statusLabel(row, s))}</b>${transport ? ' <span class="bus">🚌</span>' : ''}</span>
                </div>
            </div>`;
        }).join('');
        return openPrint('Student Basic Details', `${SHARED_CSS}
            .block { border-bottom: 1px solid #94a3b8; padding: 8px 0 10px; page-break-inside: avoid; }
            .r1, .r2 { display: flex; flex-wrap: wrap; gap: 6px 18px; align-items: baseline; margin-bottom: 4px; font-size: 11.5px; }
            .sr { border: 1px solid #94a3b8; padding: 1px 6px; font-weight: 700; font-size: 11px; }
            .ph { margin-left: auto; white-space: nowrap; }
            .addr { flex: 1 1 55%; }
            .st { margin-left: auto; }
            .ok { color: #15803d; }
            .bus { font-size: 12px; }
            b { font-weight: 700; }
        `, `${header}<div class="title-bar">STUDENT BASIC DETAILS</div>${blocks || '<p>No students.</p>'}`);
    }

    // student list
    const body = rows.map((row, i) => {
        const s = row.__raw || {};
        return `<tr>
            <td class="c">${i + 1}</td>
            <td>${esc(s.admission_no || row.admissionNo || '')}</td>
            <td>${esc(s.name || row.name || '')}</td>
            <td class="c">${esc(classSec(row))}</td>
            <td class="c">${esc(row.roll ?? s.roll_no ?? '')}</td>
            <td class="c">${esc(session)}</td>
            <td class="c"><b>${esc(statusLabel(row, s))}</b></td>
        </tr>`;
    }).join('');
    return openPrint('Student List', `${SHARED_CSS}
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #cbd5e1; padding: 7px 6px; text-align: left; vertical-align: top; font-size: 11.5px; }
        th { font-weight: 700; border-bottom: 1.5px solid #94a3b8; }
        .c { text-align: center; }
        tr { page-break-inside: avoid; }
    `, `${header}<div class="title-bar">STUDENT LIST</div>
        <table>
            <thead><tr>
                <th class="c">Sr</th><th>Adm No.</th><th>Name</th><th class="c">Class</th>
                <th class="c">Roll</th><th class="c">Session</th><th class="c">Status</th>
            </tr></thead>
            <tbody>${body || '<tr><td colspan="7">No students.</td></tr>'}</tbody>
        </table>`);
}
