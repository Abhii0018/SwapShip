@php
    $manifestPath = public_path('build/manifest.json');
    $viteReady = false;

    if (is_readable($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (is_array($manifest)) {
            $cssFile = $manifest['resources/css/app.css']['file'] ?? null;
            $jsFile = $manifest['resources/js/app.js']['file'] ?? null;
            $viteReady = is_string($cssFile)
                && is_string($jsFile)
                && is_file(public_path('build/'.$cssFile))
                && is_file(public_path('build/'.$jsFile));
        }
    }
@endphp

@if ($viteReady)
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endif
