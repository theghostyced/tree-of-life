/** The programme loop, in the order a participant actually meets it. Shared by the public landing page and the in-app help page. */
export const PROGRAMME_STEPS: { title: string; body: string }[] = [
    {
        title: 'An admin invites you',
        body: 'TLF is invitation-only. Your invitation arrives by email carrying a single-use link, and it stays valid for seven days.',
    },
    {
        title: 'You set up your profile',
        body: 'A guided wizard collects your details and documents, and shows exactly what is still missing, so nobody is left guessing whether they are ready.',
    },
    {
        title: 'You choose your mentor',
        body: 'Entrepreneurs browse approved mentors by expertise and industry and choose who to work with, several if that is what the business needs. Nobody is assigned a mentor without choosing them.',
    },
    {
        title: 'You book real availability',
        body: 'Mentors publish the hours they are free. Bookings land inside those hours, and every confirmed meeting carries its own joining link.',
    },
    {
        title: 'You talk between meetings',
        body: 'A private thread for each pairing keeps questions, context, and follow-ups in one place rather than scattered across inboxes.',
    },
    {
        title: 'The meeting leaves a report',
        body: 'The mentor writes a short written record afterwards. It is what both sides revisit later, and how the programme knows a mentorship is genuinely running.',
    },
];
