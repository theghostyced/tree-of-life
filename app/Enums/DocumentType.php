<?php

namespace App\Enums;

enum DocumentType: string
{
    case BusinessCertificate = 'business_certificate';
    case BusinessRegistrationDocuments = 'business_registration_documents';
    case BusinessPlan = 'business_plan';
    case Milestones = 'milestones';
    case OperationalPlan = 'operational_plan';
    case TechnicalSupportRequirements = 'technical_support_requirements';
    case PassportPhoto = 'passport_photo';
    case IdentificationCard = 'identification_card';
    case Certification = 'certification';

    /**
     * The document types a role must supply before submitting for review.
     *
     * @return array<int, self>
     */
    public static function requiredFor(UserRole $role): array
    {
        return match ($role) {
            // Retired types stay as cases so existing rows still cast.
            UserRole::Entrepreneur => [
                self::BusinessPlan,
                self::Milestones,
                self::OperationalPlan,
            ],
            UserRole::Mentor => [
                self::PassportPhoto,
                self::IdentificationCard,
                self::Certification,
            ],
            default => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::BusinessCertificate => 'Business Certificate',
            self::BusinessRegistrationDocuments => 'Business Registration Documents',
            self::BusinessPlan => 'Business Plan',
            self::Milestones => 'Milestones',
            self::OperationalPlan => 'Operational Plan',
            self::TechnicalSupportRequirements => 'Technical Support Requirements',
            self::PassportPhoto => 'Passport Photo',
            self::IdentificationCard => 'Identification Card',
            self::Certification => 'Certification',
        };
    }

    /**
     * What the applicant should actually upload. Shown next to the requirement
     * so people can gather the right file before they reach the upload step.
     */
    public function description(): string
    {
        return match ($this) {
            self::BusinessCertificate => 'Your certificate of business registration or incorporation.',
            self::BusinessRegistrationDocuments => 'Official documents showing the business is registered.',
            self::BusinessPlan => 'Your current business plan: what you sell, to whom, and how the business will grow over the next two to three years.',
            self::Milestones => 'The key milestones you aim to reach during the programme, with target dates.',
            self::OperationalPlan => 'How the business runs day to day: team, suppliers, production or delivery, and key processes.',
            self::TechnicalSupportRequirements => 'The technical support your business needs from the programme.',
            self::PassportPhoto => 'A recent, clear head-and-shoulders photo of yourself against a plain background.',
            self::IdentificationCard => 'A government-issued photo ID: national ID card, passport, or driver\'s licence. The whole document must be readable.',
            self::Certification => 'Your highest relevant professional qualification: a degree certificate, professional membership, or an industry certification that supports your areas of expertise.',
        };
    }

    public function role(): UserRole
    {
        return match ($this) {
            self::PassportPhoto, self::IdentificationCard, self::Certification => UserRole::Mentor,
            default => UserRole::Entrepreneur,
        };
    }

    /**
     * @return array<int, string>
     */
    public function allowedExtensions(): array
    {
        return match ($this) {
            self::PassportPhoto, self::IdentificationCard => ['pdf', 'png', 'jpg', 'jpeg'],
            default => ['pdf', 'png', 'jpg', 'jpeg', 'docx'],
        };
    }

    public function maxKilobytes(): int
    {
        return $this->role() === UserRole::Entrepreneur ? 5120 : 2048;
    }
}
