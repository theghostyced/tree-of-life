// Shared option sets for the onboarding forms. Range selects map a human range
// to a representative integer, so the backend keeps storing a simple integer.

export type RangeOption = { value: number; label: string };

export const YEAR_RANGES: RangeOption[] = [
    { value: 0, label: 'Less than 1 year' },
    { value: 2, label: '1–2 years' },
    { value: 5, label: '3–5 years' },
    { value: 10, label: '6–10 years' },
    { value: 15, label: 'More than 10 years' },
];

export const EMPLOYEE_RANGES: RangeOption[] = [
    { value: 1, label: 'Just me' },
    { value: 5, label: '2–5' },
    { value: 20, label: '6–20' },
    { value: 50, label: '21–50' },
    { value: 100, label: '51–100' },
    { value: 150, label: 'More than 100' },
];

export const SECTORS: string[] = [
    'Agriculture',
    'Manufacturing',
    'Retail & trade',
    'Technology',
    'Financial services',
    'Healthcare',
    'Education',
    'Energy',
    'Construction',
    'Transport & logistics',
    'Tourism & hospitality',
    'Creative & media',
    'Food & beverage',
    'Textiles & apparel',
];

// Mentors focus on the same industry set.
export const INDUSTRIES: string[] = SECTORS;

export const EXPERTISE_AREAS: string[] = [
    'Trade finance',
    'Market access',
    'Operations',
    'Marketing & branding',
    'Fundraising',
    'Strategy',
    'Finance & accounting',
    'Legal & compliance',
    'Technology',
    'Human resources',
    'Supply chain',
];

export const AVAILABILITY: string[] = [
    'Weekday mornings',
    'Weekday afternoons',
    'Weekday evenings',
    'Weekends',
    'Flexible / by arrangement',
];
