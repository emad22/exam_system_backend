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
        return response()->json(Partner::with('user')->orderBy('partner_name')->get());
    }

    public function getActivePartners()
    {
        return response()->json(
            Partner::whereHas('user', function($q) {
                $q->where('is_active', true);
            })
            ->orderBy('partner_name')
            ->get()
        );
    }
   

// STORE
    public function store(Request $request)
    {
        // dd($request->all());
      //  $partner = Partner::create($request->all());  // shaimaa commented this

        $validated = $request->validate([
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|nullable|email',
                'phone' => 'sometimes|nullable|string|max:20',
                'password' => 'required|min:6',
                'partner_name' => 'required|string',
                'website' => 'sometimes|nullable|string',
                'country' => 'nullable|string|max:255',
                'note' => 'nullable|string',
                'r_date' => 'nullable|string',
            ]);

            dd($validated);
         //   return DB::transaction(function () use ($validated) {

                // 1) Create User
                $user = User::create([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'email' => $validated['email'],
                    'role' => 'partner', 
                    'country' => $validated['country'],
                ]);

                // 2) Create Partner
                $partner = Partner::create([
                    'user_id' => $user->id,
                    'partner_name' => $validated['partner_name'],
                    'website' => $validated['website'],
                    'note' => $validated['note'],
                    'r_date' => $validated['r_date'],                   
                ]);

                return response()->json([
                    'message' => 'Partner created successfully',
                    'user' => $user,
                    'partner' => $partner
                ], 201);
           // });


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

        // User::whereIn('id', Student::where('partner_id', $partnerId)->pluck('user_id'))->update(['is_active' => false]);
        User::whereIn('id', Student::where('partner_id', $partnerId)->pluck('user_id'))->update(['is_active' => false]);
        
        $user = $partner->user;
        if ($user) {
            $user->is_active = false;
            $user->save();
        }

        return response()->json([
            'message' => 'All students under this partner have been deactivated.'
        ]);
    }

    public function unholdPartner($partnerId)
    {
        $partner = Partner::find($partnerId);
        if (!$partner) return response()->json(['message' => 'Partner not found'], 404);

        $user = $partner->user;
        if ($user) {
            $user->is_active = true;
            $user->save();
        }

        // Student::where('partner_id', $partnerId)->update(['is_active' => true]);
        User::whereIn('id', Student::where('partner_id', $partnerId)->pluck('user_id'))->update(['is_active' => true]);

        return response()->json(['message' => 'Partner unheld and students reactivated']);
    }
}
