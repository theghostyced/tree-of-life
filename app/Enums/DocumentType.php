<?php

namespace App\Enums;

enum DocumentType: string
{
    case BusinessCertificate = 'business_certificate';
    case BusinessRegistrationDocuments = 'business_registration_documents';
    case BusinessPlan = 'business_plan';
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
            UserRole::Entrepreneur => [
                self::BusinessCertificate,
                self::BusinessRegistrationDocuments,
                self::BusinessPlan,
                self::OperationalPlan,
                self::TechnicalSupportRequirements,
            ],
            UserRole::Mentor => [
                self::PassportPhoto,
                self::IdentificationCard,
                self::Certification,
            ],
            default => [],
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
