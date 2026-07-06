export type AttentionReschedule = {
    id: number;
    menteeName: string;
    previousStartsAt: number;
    newStartsAt: number;
    newEndsAt: number;
    reason: string | null;
};

export type MissingReport = {
    meetingId: number;
    menteeName: string;
    endedAt: number;
};

export type WeekMeeting = {
    id: number;
    menteeName: string;
    startsAt: number;
    endsAt: number;
    sessionType: string;
    location: string | null;
    meetingLink: string | null;
};

export type Mentee = {
    pairingId: number;
    name: string;
    company: string | null;
    lastMeetingAt: number | null;
    nextMeetingAt: number | null;
};

export type AvailabilitySlot = {
    id: number;
    dayOfWeek: number;
    startTime: string;
    endTime: string;
    sessionType: string;
};

export const DAY_NAMES = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
] as const;

export function meetingTime(ms: number): string {
    return new Date(ms).toLocaleString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
