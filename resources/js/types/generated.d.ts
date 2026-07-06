declare namespace App {
    namespace Enums {
        export type AccountStatus = 'approved' | 'deactivated';
        export type DocumentType =
            | 'business_certificate'
            | 'business_registration_documents'
            | 'business_plan'
            | 'operational_plan'
            | 'technical_support_requirements'
            | 'passport_photo'
            | 'identification_card'
            | 'certification';
        export type InvitationImportStatus =
            'pending' | 'processing' | 'completed' | 'failed';
        export type InvitationStatus =
            'pending' | 'accepted' | 'revoked' | 'expired';
        export type MeetingStatus = 'confirmed' | 'completed' | 'cancelled';
        export type PairingStatus = 'active' | 'ended';
        export type RescheduleStatus = 'pending' | 'accepted' | 'declined';
        export type UserRole = 'admin' | 'mentor' | 'entrepreneur' | 'employee';
    }
}
