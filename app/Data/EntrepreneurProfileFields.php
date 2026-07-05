<?php

namespace App\Data;

use App\Models\EntrepreneurProfile;
use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class EntrepreneurProfileFields implements Arrayable
{
    /**
     * @param  array<int, string>  $sector
     */
    public function __construct(
        public ?string $business_name,
        public ?string $business_description,
        public ?string $business_email,
        public ?string $business_phone_number,
        public array $sector,
        public ?int $years_in_operation,
        public ?int $employee_count,
    ) {}

    public static function fromProfile(?EntrepreneurProfile $profile): self
    {
        return new self(
            business_name: $profile?->business_name,
            business_description: $profile?->business_description,
            business_email: $profile?->business_email,
            business_phone_number: $profile?->business_phone_number,
            sector: $profile?->sector ?? [],
            years_in_operation: $profile?->years_in_operation,
            employee_count: $profile?->employee_count,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'business_name' => $this->business_name,
            'business_description' => $this->business_description,
            'business_email' => $this->business_email,
            'business_phone_number' => $this->business_phone_number,
            'sector' => $this->sector,
            'years_in_operation' => $this->years_in_operation,
            'employee_count' => $this->employee_count,
        ];
    }
}
