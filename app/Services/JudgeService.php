<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class JudgeService
{
    private ?array $cachedAvailableLanguages = null;

    private ?int $cacheTimestamp = null;

    private const CACHE_TTL = 60;

    public function getAvailableLanguages(): array
    {
        if ($this->cachedAvailableLanguages !== null && $this->isCacheValid()) {
            return $this->cachedAvailableLanguages;
        }

        $urls = $this->getJudgeUrls();
        if (empty($urls)) {
            return $this->getStaticLanguages();
        }

        $allAvailable = [];

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(2)
                    ->withHeader('X-Judge-Token', Config::get('services.judge.token'))
                    ->get(rtrim($url, '/').'/languages');

                if ($response->successful()) {
                    $languages = $response->json('languages', []);
                    if (is_array($languages)) {
                        $allAvailable = array_unique(array_merge($allAvailable, $languages));
                    }
                }
            } catch (\Exception $e) {
            }
        }

        if (! empty($allAvailable)) {
            $this->cachedAvailableLanguages = $allAvailable;
            $this->cacheTimestamp = time();

            return $this->cachedAvailableLanguages;
        }

        return $this->getStaticLanguages();
    }

    private function getJudgeUrls(): array
    {
        $urls = Config::get('services.judge.urls');

        if (is_array($urls)) {
            return array_values(array_filter($urls, fn ($u) => is_string($u) && trim($u) !== ''));
        }

        $url = Config::get('services.judge.url');

        if (is_string($url) && trim($url) !== '') {
            return [trim($url)];
        }

        $envUrls = env('JUDGE_URLS');

        if (is_string($envUrls) && trim($envUrls) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $envUrls))));
        }

        return [];
    }

    private function getStaticLanguages(): array
    {
        $languages = [];
        $dockerLanguages = Config::get('languages.dockerLanguages', []);

        foreach ($dockerLanguages as $language => $details) {
            foreach ($details['versions'] ?? [] as $version) {
                $languages[] = "{$language}-{$version}";
            }
        }

        return $languages;
    }

    private function isCacheValid(): bool
    {
        return $this->cacheTimestamp !== null && (time() - $this->cacheTimestamp) < self::CACHE_TTL;
    }
}
