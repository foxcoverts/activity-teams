<?php

namespace App\Filament\Manage\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
use App\Filament\Manage\Resources\Bookings\BookingResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $status = BookingStatus::tryFrom((string) ($data['status'] ?? $this->data['status'] ?? ''));

        if ($status !== null && $status !== BookingStatus::Tentative) {
            throw ValidationException::withMessages([
                'status' => __('A new booking starts as tentative.'),
            ]);
        }

        unset($data['status']);

        return $data;
    }
}
