<?php

namespace App\Qualifications;

class QualificationCatalogue
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $snapshot = null;

    public function __construct(private CatalogueCompiler $compiler) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return $this->snapshot ??= $this->load();
    }

    public function refresh(): void
    {
        $this->snapshot = null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function families(): array
    {
        return $this->snapshot()['families'];
    }

    /**
     * @return array<string, string>
     */
    public function familyOptions(bool $short = false): array
    {
        return collect($this->families())
            ->mapWithKeys(fn (array $family, string $id): array => [
                $id => __($short ? $family['short_label'] : $family['label']),
            ])
            ->all();
    }

    public function familySummaryLabel(string $kind): string
    {
        $family = $this->family($kind);

        return __($family['short_label'] ?? $family['label']);
    }

    /**
     * @return array<string, mixed>
     */
    public function family(string $kind): array
    {
        $family = $this->families()[$kind] ?? null;

        if (! is_array($family)) {
            throw new InvalidCatalogue("Unknown qualification kind [{$kind}].");
        }

        return $family;
    }

    public function hasFamily(string $kind): bool
    {
        return array_key_exists($kind, $this->families());
    }

    public function hasGroupSelect(string $kind): bool
    {
        return is_string($this->family($kind)['group_key'] ?? null);
    }

    /**
     * @return array<string, string>
     */
    public function groupOptions(string $kind): array
    {
        return collect($this->family($kind)['groups'])
            ->mapWithKeys(fn (array $group, string $name): array => [$name => $name])
            ->all();
    }

    public function onlyGroup(string $kind): ?string
    {
        $groups = array_keys($this->family($kind)['groups']);

        return count($groups) === 1 ? $groups[0] : null;
    }

    public function resolveGroup(string $kind, ?string $group): ?string
    {
        if (filled($group)) {
            return $group;
        }

        return $this->onlyGroup($kind);
    }

    /**
     * @return array<string, string>|array<string, array<string, string>>
     */
    public function categoryOptions(string $kind, ?string $group): array
    {
        $resolved = $this->hasGroupSelect($kind)
            ? (filled($group) ? $group : null)
            : $this->resolveGroup($kind, $group);

        if ($resolved === null) {
            return [];
        }

        $categories = $this->family($kind)['groups'][$resolved]['categories'] ?? [];

        if (! is_array($categories)) {
            return [];
        }

        $grouped = [];
        $flat = [];

        foreach ($categories as $name => $category) {
            $section = $category['section'] ?? null;
            $label = $this->nodeLabel($kind, $resolved, (string) $name);
            $flat[$name] = $label;

            if (is_string($section) && $section !== '') {
                $grouped[$section][$name] = $label;
            }
        }

        return $grouped === [] ? $flat : $grouped;
    }

    /**
     * @return list<string>
     */
    public function typesAllowed(string $kind, ?string $group, ?string $category): array
    {
        $resolved = $this->resolveGroup($kind, $group);

        if ($resolved === null) {
            return array_keys($this->snapshot()['permit_types']);
        }

        if (filled($category)) {
            $node = $this->family($kind)['groups'][$resolved]['categories'][$category] ?? null;

            return is_array($node) ? $node['types_allowed'] : [];
        }

        $union = [];

        foreach ($this->family($kind)['groups'][$resolved]['categories'] as $node) {
            foreach ($node['types_allowed'] as $type) {
                $union[$type] = true;
            }
        }

        return array_keys($union);
    }

    public function covers(string $kind, ?string $group, string $held, string $required): bool
    {
        if ($held === $required) {
            return true;
        }

        $resolved = $this->resolveGroup($kind, $group);

        if ($resolved === null) {
            return false;
        }

        $covers = $this->family($kind)['groups'][$resolved]['categories'][$held]['covers'] ?? null;

        return is_array($covers) && in_array($required, $covers, true);
    }

    public function permitTypeCovers(string $held, string $required): bool
    {
        if ($held === $required) {
            return true;
        }

        $covers = $this->snapshot()['permit_types'][$held]['covers'] ?? null;

        return is_array($covers) && in_array($required, $covers, true);
    }

    public function categoryExists(string $kind, string $group, string $category): bool
    {
        return isset($this->family($kind)['groups'][$group]['categories'][$category]);
    }

    /**
     * Display label for a stored node name (award, category, or level).
     */
    public function nodeLabel(string $kind, ?string $group, string $node): string
    {
        $resolved = $this->resolveGroup($kind, $group);
        $category = is_string($resolved)
            ? ($this->family($kind)['groups'][$resolved]['categories'][$node] ?? null)
            : null;
        $label = is_array($category) ? ($category['label'] ?? null) : null;

        if (is_string($label) && $label !== '') {
            return $label;
        }

        if (($this->family($kind)['node_key'] ?? null) === 'level') {
            return __('Level :level', ['level' => $node]);
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isValidPayload(string $kind, array $payload): bool
    {
        if (! $this->hasFamily($kind)) {
            return false;
        }

        $family = $this->family($kind);
        $groupKey = $family['group_key'];
        $nodeKey = $family['node_key'];
        $providedGroup = is_string($groupKey) ? ($payload[$groupKey] ?? null) : null;
        $group = $this->resolveGroup($kind, is_string($providedGroup) ? $providedGroup : null);
        $node = $payload[$nodeKey] ?? null;

        if (is_string($groupKey) && ! is_string($providedGroup)) {
            return false;
        }

        if (! is_string($node) || $node === '' || $group === null || ! $this->categoryExists($kind, $group, $node)) {
            return false;
        }

        if ($family['type_dimension']) {
            $type = $payload['permit_type'] ?? null;

            if (! is_string($type) || ! in_array($type, $this->typesAllowed($kind, $group, $node), true)) {
                return false;
            }
        }

        if ($family['restrictions'] && array_key_exists('restrictions', $payload) && ! is_string($payload['restrictions']) && $payload['restrictions'] !== null) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        $cached = \base_path('bootstrap/cache/qualifications.php');

        if (is_file($cached)) {
            /** @var array<string, mixed> $snapshot */
            $snapshot = require $cached;

            return $snapshot;
        }

        return $this->compiler->compile();
    }
}
