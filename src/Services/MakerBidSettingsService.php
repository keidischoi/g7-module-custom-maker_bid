<?php

namespace Modules\Custom\MakerBids\Services;

use Modules\Custom\MakerBids\Models\MakerModuleSetting;
use Modules\Custom\MakerBids\Support\SettingsRules;

class MakerBidSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function getAllSettings(): array
    {
        return SettingsRules::merge($this->loadSaved());
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        $all = $this->getAllSettings();
        $parts = explode('.', $key, 2);
        if (count($parts) === 1) {
            return $all[$key] ?? $default;
        }
        [$category, $field] = $parts;

        return $all[$category][$field] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(string $category): array
    {
        $all = $this->getAllSettings();

        return is_array($all[$category] ?? null) ? $all[$category] : [];
    }

    /**
     * Flat admin form values.
     *
     * @return array<string, mixed>
     */
    public function adminForm(): array
    {
        return SettingsRules::flatten($this->getAllSettings());
    }

    /**
     * Nested public payload for user pages / nav.js.
     *
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        return SettingsRules::publicPayload($this->getAllSettings());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function saveFromInput(array $input): array
    {
        $normalized = SettingsRules::fromInput($input);
        $this->saveSettings($normalized);

        return $this->adminForm();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function saveSettings(array $settings): bool
    {
        $normalized = SettingsRules::normalize($settings);
        foreach (SettingsRules::CATEGORIES as $category) {
            $payload = $normalized[$category] ?? [];
            $this->saveCategory($category, is_array($payload) ? $payload : []);
        }
        $this->refreshConfigMirror();

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function saveCategory(string $category, array $payload): void
    {
        try {
            MakerModuleSetting::query()->updateOrCreate(
                ['category' => $category],
                ['payload' => $payload],
            );
        } catch (\Throwable) {
        }
        $this->writeCategoryFile($category, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSaved(): array
    {
        $saved = [];
        foreach (SettingsRules::CATEGORIES as $category) {
            $row = $this->loadCategory($category);
            if ($row !== []) {
                $saved[$category] = $row;
            }
        }

        return $saved;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCategory(string $category): array
    {
        try {
            $row = MakerModuleSetting::query()->where('category', $category)->first();
            if ($row && is_array($row->payload)) {
                return $row->payload;
            }
        } catch (\Throwable) {
        }

        return $this->readCategoryFile($category);
    }

    /**
     * @return array<string, mixed>
     */
    private function readCategoryFile(string $category): array
    {
        $path = $this->categoryFile($category);
        if ($path === '' || ! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeCategoryFile(string $category, array $payload): void
    {
        $dir = $this->storageDir();
        if ($dir === '') {
            return;
        }
        try {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents(
                $dir.'/'.$category.'.json',
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            );
        } catch (\Throwable) {
        }
    }

    private function categoryFile(string $category): string
    {
        $dir = $this->storageDir();

        return $dir === '' ? '' : $dir.'/'.$category.'.json';
    }

    private function storageDir(): string
    {
        if (function_exists('storage_path')) {
            try {
                return storage_path('app/modules/'.SettingsRules::MODULE_ID.'/settings');
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }

    private function refreshConfigMirror(): void
    {
        if (function_exists('g7_refresh_module_settings_config')) {
            try {
                g7_refresh_module_settings_config(SettingsRules::MODULE_ID);
            } catch (\Throwable) {
            }
        }
    }
}
