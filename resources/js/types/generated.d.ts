declare namespace App {
    namespace Enums {
        export type AccountStatus =
            'draft' | 'pending' | 'approved' | 'rejected' | 'deactivated';
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
        export type UserRole = 'admin' | 'mentor' | 'entrepreneur' | 'employee';
    }
}
