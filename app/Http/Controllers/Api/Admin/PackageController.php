<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;

class PackageController extends Controller
{
    /**
     * Get all packages
     */
    public function index()
    {
        return response()->json(Package::orderBy('skills_count')->get());
    }
}
