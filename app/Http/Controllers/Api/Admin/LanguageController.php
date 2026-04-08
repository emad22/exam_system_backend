<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;

class LanguageController extends Controller
{
    /**
     * Get all languages for dropdown
     */
    public function index()
    {
        return response()->json(Language::orderBy('name')->get());
    }
}
