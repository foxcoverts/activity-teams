<?php

namespace App\Filament\Concerns;

use App\Actions\AcknowledgePersonalDataAction;
use App\Enums\PersonalDataSection;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * For view and edit pages whose record is a person.
 */
trait AcknowledgesPersonalData
{
    public function acknowledgePersonalData(string $section): void
    {
        $section = PersonalDataSection::tryFrom($section);
        $record = $this->getRecord();
        $viewer = auth()->user();

        abort_unless($section instanceof PersonalDataSection && $record instanceof User && $viewer instanceof User, 404);

        Gate::authorize('view', $record);

        app(AcknowledgePersonalDataAction::class)($viewer, $record, $section);

        if ($this instanceof EditRecord) {
            $this->data = array_merge($this->data, Arr::only($record->attributesToArray(), $section->attributes()));
        }
    }

    /**
     * The page's form state is part of the Livewire payload, so keep values the viewer has not acknowledged out of it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $viewer = auth()->user();

        foreach (PersonalDataSection::cases() as $section) {
            if (! $record instanceof User || ! $viewer instanceof User || ! $viewer->hasAcknowledgedPersonalData($record, $section)) {
                $data = Arr::except($data, $section->attributes());
            }
        }

        return $data;
    }
}
