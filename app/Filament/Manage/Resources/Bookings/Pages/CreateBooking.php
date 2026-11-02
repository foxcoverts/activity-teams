<?php

namespace App\Filament\Manage\Resources\Bookings\Pages;

use App\Actions\SaveBookingDetailsAction;
use App\Enums\BookingStatus;
use App\Filament\Manage\Resources\Bookings\BookingResource;
use App\Models\Team;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
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

        return BookingResource::mergeScheduleData($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $team = Filament::getTenant();
        abort_unless($team instanceof Team, 404);

        return app(SaveBookingDetailsAction::class)->create($data, $team);
    }
}
