<?php

namespace App\Qualifications;

use Symfony\Component\Yaml\Yaml;

class CatalogueCompiler
{
    public function __construct(
        private ?string $directory = null,
        private ?string $cachePath = null,
    ) {
        $this->directory ??= \resource_path('data/qualifications');
        $this->cachePath ??= \base_path('bootstrap/cache/qualifications.php');
    }

    /**
     * @return array<string, mixed>
     */
    public function compile(): array
    {
        $permitTypes = $this->compilePermitTypes();
        $index = $this->parse($this->directory.'/families.yaml');
        $families = [];

        foreach ($index['families'] ?? [] as $family) {
            if (! is_array($family) || blank($family['id'] ?? null) || blank($family['file'] ?? null)) {
                throw new InvalidCatalogue('Each family needs an id and a file.');
            }

            $families[$family['id']] = $this->compileFamily($family, array_keys($permitTypes));
        }

        return [
            'permit_types' => $permitTypes,
            'families' => $families,
        ];
    }

    public function write(): string
    {
        $export = var_export($this->compile(), true);

        file_put_contents($this->cachePath, "<?php\n\nreturn {$export};\n");

        return $this->cachePath;
    }

    public function clear(): void
    {
        if (is_file($this->cachePath)) {
            unlink($this->cachePath);
        }
    }

    /**
     * @return array<string, array{covers: list<string>}>
     */
    private function compilePermitTypes(): array
    {
        $document = $this->parse($this->directory.'/permit-types.yaml');
        $graph = [];

        foreach ($document['permit_types'] ?? [] as $type) {
            if (! is_array($type) || blank($type['id'] ?? null)) {
                throw new InvalidCatalogue('Each permit type needs an id.');
            }

            $graph[$type['id']] = array_values(array_filter(
                $type['covers'] ?? [],
                fn (mixed $name): bool => is_string($name) && $name !== '',
            ));
        }

        foreach ($graph as $id => $covers) {
            foreach ($covers as $covered) {
                if (! array_key_exists($covered, $graph)) {
                    throw new InvalidCatalogue("Permit type [{$id}] covers unknown type [{$covered}].");
                }
            }
        }

        $expanded = [];

        foreach (array_keys($graph) as $id) {
            $expanded[$id] = [
                'covers' => $this->expand($id, $graph),
            ];
        }

        return $expanded;
    }

    /**
     * @param  array<string, mixed>  $family
     * @param  list<string>  $permitTypeIds
     * @return array<string, mixed>
     */
    private function compileFamily(array $family, array $permitTypeIds): array
    {
        $document = $this->parse($this->directory.'/'.$family['file']);
        $groups = [];

        foreach ($document['groups'] ?? [] as $group) {
            if (! is_array($group) || blank($group['name'] ?? null)) {
                throw new InvalidCatalogue("Family [{$family['id']}] has a group without a name.");
            }

            $groupName = $group['name'];
            $groups[$groupName] = $this->compileGroup($family['id'], $groupName, $group['categories'] ?? [], $permitTypeIds);
        }

        if ($groups === []) {
            throw new InvalidCatalogue("Family [{$family['id']}] has no groups.");
        }

        $label = $family['label'] ?? $family['id'];

        return [
            'id' => $family['id'],
            'label' => $label,
            'short_label' => $family['short_label'] ?? $label,
            'expires' => (bool) ($family['expires'] ?? true),
            'restrictions' => (bool) ($family['restrictions'] ?? false),
            'unique' => (bool) ($family['unique'] ?? false),
            'type_dimension' => (bool) ($family['type_dimension'] ?? false),
            'group_key' => $family['group_key'] ?? null,
            'node_key' => $family['node_key'] ?? 'category',
            'groups' => $groups,
        ];
    }

    /**
     * @param  list<mixed>  $categories
     * @param  list<string>  $permitTypeIds
     * @return array{name: string, categories: array<string, array<string, mixed>>}
     */
    private function compileGroup(string $familyId, string $groupName, array $categories, array $permitTypeIds): array
    {
        $graph = [];
        $nodes = [];

        foreach ($categories as $category) {
            if (! is_array($category) || blank($category['name'] ?? null)) {
                throw new InvalidCatalogue("Group [{$groupName}] in [{$familyId}] has a category without a name.");
            }

            $name = $category['name'];

            if (array_key_exists($name, $nodes)) {
                throw new InvalidCatalogue("Duplicate category [{$name}] in [{$familyId}] group [{$groupName}].");
            }

            $covers = array_values(array_filter(
                $category['covers'] ?? [],
                fn (mixed $covered): bool => is_string($covered) && $covered !== '' && $covered !== $name,
            ));

            $graph[$name] = $covers;
            $nodes[$name] = [
                'name' => $name,
                'section' => $category['section'] ?? null,
                'types_allowed' => $this->typesAllowed($familyId, $groupName, $name, $category['types_allowed'] ?? null, $permitTypeIds),
            ];
        }

        foreach ($graph as $name => $covers) {
            foreach ($covers as $covered) {
                if (! array_key_exists($covered, $graph)) {
                    throw new InvalidCatalogue("[{$familyId}] [{$groupName}] [{$name}] covers unknown category [{$covered}].");
                }
            }
        }

        foreach (array_keys($graph) as $name) {
            $nodes[$name]['covers'] = $this->expand($name, $graph);
        }

        return [
            'name' => $groupName,
            'categories' => $nodes,
        ];
    }

    /**
     * @param  list<string>|null  $allowed
     * @param  list<string>  $permitTypeIds
     * @return list<string>
     */
    private function typesAllowed(string $familyId, string $groupName, string $category, mixed $allowed, array $permitTypeIds): array
    {
        if ($allowed === null) {
            return $permitTypeIds;
        }

        if (! is_array($allowed)) {
            throw new InvalidCatalogue("[{$familyId}] [{$groupName}] [{$category}] types_allowed must be a list.");
        }

        $normalized = [];

        foreach ($allowed as $type) {
            if (! is_string($type) || ! in_array($type, $permitTypeIds, true)) {
                throw new InvalidCatalogue("[{$familyId}] [{$groupName}] [{$category}] has unknown permit type [{$type}].");
            }

            $normalized[] = $type;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<string, list<string>>  $graph
     * @param  array<string, true>  $stack
     * @param  array<string, list<string>>  $memo
     * @return list<string>
     */
    private function expand(string $name, array $graph, array &$stack = [], array &$memo = []): array
    {
        if (array_key_exists($name, $memo)) {
            return $memo[$name];
        }

        if (isset($stack[$name])) {
            throw new InvalidCatalogue("Cover graph has a cycle at [{$name}].");
        }

        $stack[$name] = true;
        $covers = [];

        foreach ($graph[$name] ?? [] as $child) {
            $covers[] = $child;
            $covers = [...$covers, ...$this->expand($child, $graph, $stack, $memo)];
        }

        unset($stack[$name]);

        $memo[$name] = array_values(array_unique($covers));

        return $memo[$name];
    }

    /**
     * @return array<string, mixed>
     */
    private function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidCatalogue("Missing catalogue file [{$path}].");
        }

        $document = Yaml::parseFile($path);

        if (! is_array($document)) {
            throw new InvalidCatalogue("Catalogue file [{$path}] is empty.");
        }

        return $document;
    }
}
