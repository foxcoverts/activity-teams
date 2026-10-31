<?php

namespace App\Filament\Qualifications;

use App\Models\ActivityQualificationRequirement;
use App\Models\Qualification;
use App\Models\User;
use App\Qualifications\PermitType;
use App\Qualifications\QualificationCatalogue;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class QualificationSchema
{
    /**
     * @return array<int, DatePicker|Grid|Select|Textarea>
     */
    public static function memberFields(?User $member = null, ?Qualification $record = null): array
    {
        return [
            static::kindSelect($record === null),
            static::groupSelect(required: true),
            Grid::make(2)
                ->schema([
                    static::nodeSelect(required: true, member: $member, record: $record),
                    static::typeSelect(required: true),
                ]),
            Textarea::make('restrictions')
                ->placeholder(__('None'))
                ->rows(2)
                ->nullable()
                ->visible(fn (Get $get): bool => static::familyFlag($get('kind'), 'restrictions')),
            DatePicker::make('expires_on')
                ->label(__('Expires'))
                ->visible(fn (Get $get): bool => static::familyFlag($get('kind'), 'expires'))
                ->required(fn (Get $get): bool => static::familyFlag($get('kind'), 'expires')),
        ];
    }

    /**
     * @return array<int, Grid|Select>
     */
    public static function requirementFields(): array
    {
        return [
            static::kindSelect(true),
            static::groupSelect(required: false),
            Grid::make(2)
                ->schema([
                    static::nodeSelect(required: false),
                    static::typeSelect(required: false),
                ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function packRecord(array $data): array
    {
        return [
            'kind' => $data['kind'],
            'payload' => static::packDimensions($data),
            'expires_on' => $data['expires_on'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function unpackRecord(array $data): array
    {
        return [
            ...$data,
            ...static::unpackDimensions($data['kind'] ?? null, $data['payload'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function packRequirement(array $data): array
    {
        return [
            'kind' => $data['kind'] ?? null,
            'criteria' => static::packDimensions($data),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function unpackRequirement(array $data): array
    {
        return [
            ...$data,
            ...static::unpackDimensions($data['kind'] ?? null, $data['criteria'] ?? []),
        ];
    }

    public static function requirementRepeater(): Repeater
    {
        return Repeater::make('qualificationRequirements')
            ->relationship()
            ->orderColumn('sort')
            ->label(__('Acceptable qualifications'))
            ->helperText(__('Each row is one way to qualify. Leave a field blank for any value. A higher category, permit type, level, or award covers a lower one. Permit restrictions are ignored. Leave this empty when the activity needs no qualification.'))
            ->schema(static::requirementFields())
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => static::packRequirement($data))
            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => static::packRequirement($data))
            ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => static::unpackRequirement($data))
            ->itemLabel(function (array $state): ?string {
                if (blank($state['kind'] ?? null)) {
                    return null;
                }

                return (new ActivityQualificationRequirement(static::packRequirement($state)))->summary;
            })
            ->addActionLabel(__('Add alternative'))
            ->defaultItems(0)
            ->columnSpanFull();
    }

    private static function kindSelect(bool $editable): Select
    {
        $select = Select::make('kind')
            ->label(__('Type'))
            ->options(fn (): array => app(QualificationCatalogue::class)->familyOptions())
            ->live()
            ->required()
            ->afterStateUpdated(function (Set $set): void {
                $set('group', null);
                $set('node', null);
                $set('permit_type', null);
                $set('restrictions', null);
                $set('expires_on', null);
            });

        if (! $editable) {
            $select->disabled()->dehydrated();
        }

        return $select;
    }

    private static function groupSelect(bool $required): Select
    {
        return Select::make('group')
            ->label(fn (Get $get): string => static::family($get('kind'))['group_key'] === 'scheme' ? __('Scheme') : __('Activity'))
            ->options(function (Get $get): array {
                $kind = $get('kind');

                if (! is_string($kind) || ! app(QualificationCatalogue::class)->hasFamily($kind)) {
                    return [];
                }

                return app(QualificationCatalogue::class)->groupOptions($kind);
            })
            ->searchable()
            ->live()
            ->visible(function (Get $get): bool {
                $kind = $get('kind');

                return is_string($kind)
                    && app(QualificationCatalogue::class)->hasFamily($kind)
                    && app(QualificationCatalogue::class)->hasGroupSelect($kind);
            })
            ->required(fn (Get $get): bool => $required && filled($get('kind')))
            ->afterStateUpdated(function (Set $set): void {
                $set('node', null);
                $set('permit_type', null);
            });
    }

    private static function nodeSelect(bool $required, ?User $member = null, ?Qualification $record = null): Select
    {
        return Select::make('node')
            ->label(function (Get $get): string {
                return match (static::family($get('kind'))['node_key'] ?? 'category') {
                    'award' => __('Award'),
                    'level' => __('Level'),
                    default => __('Category'),
                };
            })
            ->options(function (Get $get): array {
                $kind = $get('kind');

                if (! is_string($kind) || ! app(QualificationCatalogue::class)->hasFamily($kind)) {
                    return [];
                }

                return app(QualificationCatalogue::class)->categoryOptions($kind, $get('group'));
            })
            ->searchable()
            ->live()
            ->visible(fn (Get $get): bool => static::nodeVisible($get))
            ->required(fn (Get $get): bool => $required && static::nodeVisible($get))
            ->disableOptionWhen(function (Get $get, mixed $value) use ($member, $record): bool {
                $kind = $get('kind');

                if (! $member instanceof User || ! is_string($kind) || ! static::familyFlag($kind, 'unique') || blank($value)) {
                    return false;
                }

                return Qualification::query()
                    ->where('user_id', $member->getKey())
                    ->where('kind', $kind)
                    ->where('payload->'.static::family($kind)['node_key'], $value)
                    ->when($record?->getKey(), fn ($query, string $id) => $query->whereKeyNot($id))
                    ->exists();
            })
            ->afterStateUpdated(function (Set $set): void {
                $set('permit_type', null);
            });
    }

    private static function typeSelect(bool $required): Select
    {
        return Select::make('permit_type')
            ->label(__('Permit type'))
            ->options(function (Get $get): array {
                $kind = $get('kind');

                if (! is_string($kind) || ! static::familyFlag($kind, 'type_dimension')) {
                    return [];
                }

                $options = [];

                foreach (app(QualificationCatalogue::class)->typesAllowed($kind, $get('group'), $get('node')) as $id) {
                    $options[$id] = PermitType::label($id);
                }

                return $options;
            })
            ->live()
            ->visible(fn (Get $get): bool => static::familyFlag($get('kind'), 'type_dimension') && static::nodeVisible($get))
            ->required(fn (Get $get): bool => $required && static::familyFlag($get('kind'), 'type_dimension') && static::nodeVisible($get));
    }

    private static function nodeVisible(Get $get): bool
    {
        $kind = $get('kind');

        if (! is_string($kind) || ! app(QualificationCatalogue::class)->hasFamily($kind)) {
            return false;
        }

        if (! app(QualificationCatalogue::class)->hasGroupSelect($kind)) {
            return true;
        }

        return filled($get('group'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function family(?string $kind): array
    {
        if (! is_string($kind) || ! app(QualificationCatalogue::class)->hasFamily($kind)) {
            return [
                'group_key' => null,
                'node_key' => 'category',
                'expires' => false,
                'restrictions' => false,
                'unique' => false,
                'type_dimension' => false,
            ];
        }

        return app(QualificationCatalogue::class)->family($kind);
    }

    private static function familyFlag(mixed $kind, string $flag): bool
    {
        return is_string($kind) && (bool) (static::family($kind)[$flag] ?? false);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function packDimensions(array $data): array
    {
        $kind = $data['kind'] ?? null;

        if (! is_string($kind) || ! app(QualificationCatalogue::class)->hasFamily($kind)) {
            return [];
        }

        $family = app(QualificationCatalogue::class)->family($kind);
        $payload = [];

        if (is_string($family['group_key']) && filled($data['group'] ?? null)) {
            $payload[$family['group_key']] = $data['group'];
        }

        if (filled($data['node'] ?? null)) {
            $payload[$family['node_key']] = $data['node'];
        }

        if ($family['type_dimension'] && filled($data['permit_type'] ?? null)) {
            $payload['permit_type'] = $data['permit_type'];
        }

        if ($family['restrictions'] && filled($data['restrictions'] ?? null)) {
            $payload['restrictions'] = $data['restrictions'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $dimensions
     * @return array<string, mixed>
     */
    private static function unpackDimensions(?string $kind, ?array $dimensions): array
    {
        if (! is_string($kind) || ! is_array($dimensions) || ! app(QualificationCatalogue::class)->hasFamily($kind)) {
            return [];
        }

        $family = app(QualificationCatalogue::class)->family($kind);

        return array_filter([
            'group' => is_string($family['group_key']) ? ($dimensions[$family['group_key']] ?? null) : null,
            'node' => $dimensions[$family['node_key']] ?? null,
            'permit_type' => $dimensions['permit_type'] ?? null,
            'restrictions' => $dimensions['restrictions'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
