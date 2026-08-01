<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;

test('the entrepreneur required document set is complete and exact', function () {
    expect(DocumentType::requiredFor(UserRole::Entrepreneur))->toEqualCanonicalizing([
        DocumentType::BusinessPlan,
        DocumentType::Milestones,
        DocumentType::OperationalPlan,
    ]);
});

test('the mentor required document set is complete and exact', function () {
    expect(DocumentType::requiredFor(UserRole::Mentor))->toEqualCanonicalizing([
        DocumentType::PassportPhoto,
        DocumentType::IdentificationCard,
        DocumentType::Certification,
    ]);
});

test('roles without an onboarding document set require nothing', function (string $role) {
    expect(DocumentType::requiredFor(UserRole::from($role)))->toBe([]);
})->with(['admin', 'employee']);

test('each document type carries its own file constraints', function (string $type, array $extensions, int $maxKilobytes) {
    $case = DocumentType::from($type);

    expect($case->allowedExtensions())->toEqualCanonicalizing($extensions)
        ->and($case->maxKilobytes())->toBe($maxKilobytes);
})->with([
    'business certificate' => ['business_certificate', ['pdf', 'png', 'jpg', 'jpeg', 'docx'], 5120],
    'business plan' => ['business_plan', ['pdf', 'png', 'jpg', 'jpeg', 'docx'], 5120],
    'technical support' => ['technical_support_requirements', ['pdf', 'png', 'jpg', 'jpeg', 'docx'], 5120],
    'passport photo' => ['passport_photo', ['pdf', 'png', 'jpg', 'jpeg'], 2048],
    'identification card' => ['identification_card', ['pdf', 'png', 'jpg', 'jpeg'], 2048],
    'certification' => ['certification', ['pdf', 'png', 'jpg', 'jpeg', 'docx'], 2048],
]);

test('a document type belongs to exactly one onboarding role', function () {
    expect(DocumentType::BusinessPlan->role())->toBe(UserRole::Entrepreneur)
        ->and(DocumentType::PassportPhoto->role())->toBe(UserRole::Mentor);
});
