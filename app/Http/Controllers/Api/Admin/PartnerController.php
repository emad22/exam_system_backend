<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Partner\StorePartnerRequest;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\Request;

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
    public function store(StorePartnerRequest $request)
    {
        $validated = $request->validated();

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
    }

// SHOW
    public function show($id)
    {
        return Partner::with('user')->findOrFail($id);
    }

// UPDATE
    public function update(Request $request, $id)
    {
        $partner = Partner::with('user')->findOrFail($id);
        $data = $request->all();

        if (isset($data['proctoring_mode'])) {
            $data['proctoring_required'] = in_array($data['proctoring_mode'], ['full', 'identity_only'], true);
        }

        // Update partner fields
        $partner->update($data);

        // Sync user fields if a linked user exists
        if ($partner->user) {
            $userFields = [];
            if (isset($data['fName_contact'])) $userFields['first_name'] = $data['fName_contact'];
            if (isset($data['lName_contact'])) $userFields['last_name']  = $data['lName_contact'];
            if (isset($data['email']))          $userFields['email']      = $data['email'];
            if (isset($data['phone']))          $userFields['phone']      = $data['phone'];
            if (isset($data['country']))        $userFields['country']    = $data['country'];
            if (isset($data['is_active']))      $userFields['is_active']  = (bool) $data['is_active'];

            if (!empty($userFields)) {
                $partner->user->update($userFields);
            }
        }

        return response()->json([
            'message' => 'Partner updated successfully',
            'partner' => $partner->fresh('user'),
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
