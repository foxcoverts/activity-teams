<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class PrivacyPolicyController extends Controller
{
    public function __invoke(): View
    {
        $path = resource_path('markdown/privacy-policy.md');

        abort_unless(File::exists($path), 404);

        return view('privacy-policy', [
            'content' => new HtmlString(Str::markdown(File::get($path), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ])),
        ]);
    }
}
