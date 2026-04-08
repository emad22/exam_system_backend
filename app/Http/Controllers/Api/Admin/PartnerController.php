<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Partner;



class PartnerController extends Controller
{
    //
    public function index()
{
    return Partner::all();
}

// STORE
public function store(Request $request)
{
    $partner = Partner::create($request->all());
    return response()->json($partner);
}

// SHOW
public function show($id)
{
    return Partner::findOrFail($id);
}

// UPDATE
public function update(Request $request, $id)
{
    $partner = Partner::findOrFail($id);
    $partner->update($request->all());
    return response()->json($partner);
}

// DELETE
public function destroy($id)
{
    Partner::destroy($id);
    return response()->json(['message' => 'Deleted']);
}

}
