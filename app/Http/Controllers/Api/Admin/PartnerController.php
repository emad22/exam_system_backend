<?php

namespace App\Http\Controllers\Api\Admin;


use App\Models\Partner;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;

class PartnerController extends Controller
{
    //
    public function index()
    {
        // return Partner::all();
        return response()->json(Partner::orderBy('partner_name')->get());
        
    }

   public function getActivePartners()
{
    return response()->json(
        Partner::where('is_active', true)
               ->orderBy('partner_name')
               ->get()
    );
}
   

// STORE
    public function store(Request $request)
    {
        // dd($request->all());
        $partner = Partner::create($request->all());
        return response()->json([
            'message' => 'Partner created successfully',
            'partner' => $partner
        ], 201);
            
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
        return response()->json([
                'message' => 'Partner updated successfully',
                'partner' => $partner
            ]);
    }

// DELETE
    public function destroy($id)
    {
        Partner::destroy($id);
        return response()->json(['message' => 'Partner and all related content deleted successfully.']);

    }




    public function deactivatePartnerStudents($partnerId)
    {
        // تحقق أن الـ partner موجود
        $partner = Partner::find($partnerId);
    
        if (!$partner) {
            return response()->json(['message' => 'Partner not found'], 404);
        }

        // Student::where('partner_id', $partnerId)->update(['is_active' => false]);
        User::whereIn('id', Student::where('partner_id', $partnerId)->pluck('user_id'))->update(['is_active' => false]);
        $partner->update(['is_active' => false]);

        return response()->json([
            'message' => 'All students under this partner have been deactivated.'
        ]);
    }

    public function unholdPartner($partnerId)
    {
        $partner = Partner::find($partnerId);
        if (!$partner) return response()->json(['message' => 'Partner not found'], 404);

        $partner->update(['is_active' => true]);

        // Student::where('partner_id', $partnerId)->update(['is_active' => true]);
        User::whereIn('id', Student::where('partner_id', $partnerId)->pluck('user_id'))->update(['is_active' => true]);

        return response()->json(['message' => 'Partner unheld and students reactivated']);
    }
}
