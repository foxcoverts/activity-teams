<?php

namespace App\LegacyImport;

use App\LegacyImport\Contracts\Importer;
use App\LegacyImport\Importers\BookingChangesImporter;
use App\LegacyImport\Importers\BookingsImporter;
use App\LegacyImport\Importers\CatalogImporter;
use App\LegacyImport\Importers\DocumentsImporter;
use App\LegacyImport\Importers\KeysImporter;
use App\LegacyImport\Importers\KitChecksImporter;
use App\LegacyImport\Importers\NewsImporter;
use App\LegacyImport\Importers\NotificationSettingsImporter;
use App\LegacyImport\Importers\QualificationsImporter;
use App\LegacyImport\Importers\TodosImporter;
use App\LegacyImport\Importers\UsersImporter;
use InvalidArgumentException;

final class LegacyImportRegistry
{
    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->definitions());
    }

    /**
     * @return list<string>
     */
    public function coreKeys(): array
    {
        return array_keys(array_filter(
            $this->definitions(),
            fn (array $definition): bool => $definition['core'] === true,
        ));
    }

    /**
     * @param  list<string>|null  $only
     * @return list<Importer>
     */
    public function resolve(?array $only = null): array
    {
        $definitions = $this->definitions();
        $keys = $only === null || $only === []
            ? $this->coreKeys()
            : $only;

        $importers = [];

        foreach ($keys as $key) {
            if (! isset($definitions[$key])) {
                throw new InvalidArgumentException("Unknown legacy importer [{$key}].");
            }

            /** @var class-string<Importer> $class */
            $class = $definitions[$key]['class'];
            $importers[] = app($class);
        }

        return $importers;
    }

    /**
     * @return array<string, array{class: class-string<Importer>, core: bool}>
     */
    private function definitions(): array
    {
        return [
            'catalog' => ['class' => CatalogImporter::class, 'core' => true],
            'users' => ['class' => UsersImporter::class, 'core' => true],
            'qualifications' => ['class' => QualificationsImporter::class, 'core' => true],
            'keys' => ['class' => KeysImporter::class, 'core' => true],
            'bookings' => ['class' => BookingsImporter::class, 'core' => true],
            'news' => ['class' => NewsImporter::class, 'core' => true],
            'documents' => ['class' => DocumentsImporter::class, 'core' => true],
            'notifications' => ['class' => NotificationSettingsImporter::class, 'core' => true],
            'changes' => ['class' => BookingChangesImporter::class, 'core' => true],
            'todos' => ['class' => TodosImporter::class, 'core' => false],
            'kit_checks' => ['class' => KitChecksImporter::class, 'core' => false],
        ];
    }
}
