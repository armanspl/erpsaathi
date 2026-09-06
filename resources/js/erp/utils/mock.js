// Deterministic, dependency-free mock data generators for the frontend-only ERP demo.

const FIRST_NAMES = ['Aarav', 'Vivaan', 'Aditya', 'Vihaan', 'Arjun', 'Sai', 'Reyansh', 'Krishna', 'Ishaan', 'Rohan', 'Ananya', 'Diya', 'Saanvi', 'Aadhya', 'Kiara', 'Myra', 'Pari', 'Anika', 'Riya', 'Navya'];
const LAST_NAMES = ['Sharma', 'Verma', 'Gupta', 'Singh', 'Kumar', 'Patel', 'Yadav', 'Mishra', 'Reddy', 'Nair', 'Iyer', 'Chauhan', 'Malhotra', 'Kapoor', 'Joshi'];
const STATUSES = ['Active', 'Inactive'];
const CLASSES = ['Nursery', 'LKG', 'UKG', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
const SECTIONS = ['A', 'B', 'C', 'D'];
const CITIES = ['Delhi', 'Mumbai', 'Jaipur', 'Lucknow', 'Pune', 'Indore', 'Chandigarh', 'Bhopal'];

function lcg(seed) {
    let s = seed % 2147483647;
    if (s <= 0) s += 2147483646;
    return () => {
        s = (s * 16807) % 2147483647;
        return (s - 1) / 2147483646;
    };
}

function pick(rand, arr) {
    return arr[Math.floor(rand() * arr.length)];
}

export function seededRandom(seed) {
    return lcg(seed || 1);
}

export function randomName(rand) {
    return `${pick(rand, FIRST_NAMES)} ${pick(rand, LAST_NAMES)}`;
}

export function randomPhone(rand) {
    return `9${Math.floor(100000000 + rand() * 899999999)}`.slice(0, 10);
}

export function randomDate(rand, daysBack = 365) {
    const d = new Date();
    d.setDate(d.getDate() - Math.floor(rand() * daysBack));
    return d.toISOString().slice(0, 10);
}

export function randomStatus(rand, weightActive = 0.8) {
    return rand() < weightActive ? 'Active' : 'Inactive';
}

export function randomCity(rand) {
    return pick(rand, CITIES);
}

export function randomClass(rand) {
    return pick(rand, CLASSES);
}

export function randomSection(rand) {
    return pick(rand, SECTIONS);
}

export function randomAmount(rand, min = 500, max = 50000) {
    return Math.floor(min + rand() * (max - min));
}

export function initials(name) {
    return name
        .split(' ')
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

/**
 * Generate `count` mock rows shaped by `columns` ([{ key, type }]).
 * Deterministic per seed so a page looks the same across reloads.
 */
export function generateRows(columns, count, seed = 42) {
    const rand = seededRandom(seed);
    const rows = [];
    for (let i = 0; i < count; i++) {
        const row = { id: i + 1 };
        for (const col of columns) {
            switch (col.type) {
                case 'photo':
                    row[col.key] = null;
                    row.__name = row.__name || randomName(rand);
                    break;
                case 'name':
                    row[col.key] = randomName(rand);
                    row.__name = row[col.key];
                    break;
                case 'code':
                    row[col.key] = `${(col.prefix || 'REC').toUpperCase()}-${String(1000 + i)}`;
                    break;
                case 'class':
                    row[col.key] = randomClass(rand);
                    break;
                case 'section':
                    row[col.key] = randomSection(rand);
                    break;
                case 'phone':
                    row[col.key] = randomPhone(rand);
                    break;
                case 'date':
                    row[col.key] = randomDate(rand);
                    break;
                case 'status':
                    row[col.key] = randomStatus(rand);
                    break;
                case 'amount':
                    row[col.key] = randomAmount(rand);
                    break;
                case 'city':
                    row[col.key] = randomCity(rand);
                    break;
                case 'number':
                    row[col.key] = Math.floor(rand() * 100);
                    break;
                default:
                    row[col.key] = `${col.label} ${i + 1}`;
            }
        }
        rows.push(row);
    }
    return rows;
}
