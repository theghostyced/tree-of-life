<?php

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/** An approved mentor who has set up their mentoring profile (available to pair). */
function availableMentor(string $name = 'Grace Mentor'): User
{
    $mentor = User::factory()->mentor()->approved()->create(['name' => $name]);
    $mentor->mentorProfile->update([
        'primary_expertise' => 'Trade finance',
        'industry_focus' => ['Agriculture'],
        'years_experience' => 10,
        'afcfta_knowledge' => 'Deep experience across the AfCFTA.',
        'availability' => 'Weekday evenings',
    ]);

    return $mentor;
}

/** An entrepreneur who has fully completed onboarding (fields + documents). */
function completeEntrepreneur(): User
{
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    $entrepreneur->entrepreneurProfile->update([
        'business_name' => 'Acme Textiles',
        'business_description' => 'We weave sustainable textiles.',
        'business_email' => 'hello@acme.co',
        'business_phone_number' => '+254700111222',
        'sector' => ['Manufacturing'],
        'years_in_operation' => 5,
        'employee_count' => 20,
    ]);
    foreach (DocumentType::requiredFor(UserRole::Entrepreneur) as $type) {
        UserDocument::factory()->for($entrepreneur)->create(['document_type' => $type]);
    }

    return $entrepreneur->refresh();
}
