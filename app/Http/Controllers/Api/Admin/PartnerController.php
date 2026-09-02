<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerResource;
use App\Http\Requests\Admin\Partner\StorePartnerRequest;
use App\Models\Partner;
use App\Models\User;
use App\Models\Student;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    //
    public function index()
    {
        $this->authorize('viewAny', Partner::class);
        return PartnerResource::collection(
            Partner::with('user')->orderBy('partner_name')->get()
        );
    }

    public function getActivePartners()
    {
        $this->authorize('viewAny', Partner::class);
        return PartnerResource::collection(
            Partner::whereHas('user', fn($q) => $q->where('is_active', true))
                ->with('user')
                ->orderBy('partner_name')
                ->get()
        );
    }
   

// STORE
    public function store(StorePartnerRequest $request)
    {
        $this->authorize('create', Partner::class);
        $validated = $request->validated();

        $firstName = $validated['first_name'] ?? $request->input('first_name') ?? $request->input('fName_contact') ?? 'Partner';
        $lastName  = $validated['last_name']  ?? $request->input('last_name')  ?? $request->input('lName_contact') ?? 'Admin';
        $email     = $validated['email'] ?? $request->input('email');
        $phone     = $validated['phone'] ?? $request->input('phone');
        $password  = !empty($validated['password']) ? bcrypt($validated['password']) : bcrypt('Partner@123456');

        $user = User::create([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'phone'      => $phone,
            'password'   => $password,
            'role'       => 'partner', 
            'country'    => $validated['country'] ?? $request->input('country'),
            'is_active'  => $request->has('is_active') ? (bool)$request->input('is_active') : true,
        ]);

        $proctoringMode = $request->input('proctoring_mode', 'none');
        $proctoringRequired = in_array($proctoringMode, ['full', 'identity_only'], true);

        // 2) Create Partner
        $partner = Partner::create([
            'user_id'             => $user->id,
            'partner_name'        => $validated['partner_name'] ?? $request->input('partner_name'),
            'website'             => $validated['website'] ?? $request->input('website'),
            'note'                => $validated['note'] ?? $request->input('note'),
            'r_date'              => $validated['r_date'] ?? now()->toDateString(),
            'proctoring_mode'     => $proctoringMode,
            'proctoring_required' => $proctoringRequired,
        ]);

        return response()->json([
            'message' => 'Partner created successfully',
            'user'    => new \App\Http\Resources\UserResource($user),
            'partner' => new PartnerResource($partner->load('user')),
        ], 201);
    }

// SHOW
    public function show($id)
    {
        $partner = Partner::with('user')->findOrFail($id);
        $this->authorize('view', $partner);
        return new PartnerResource($partner);
    }

// UPDATE
    public function update(Request $request, $id)
    {
        $partner = Partner::with('user')->findOrFail($id);
        $this->authorize('update', $partner);
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
            'partner' => new PartnerResource($partner->fresh('user')),
        ]);
    }

// DELETE
    public function destroy($id)
    {
        $partner = Partner::findOrFail($id);
        $this->authorize('delete', $partner);
        Partner::destroy($id);
        return response()->json(['message' => 'Partner and all related content deleted successfully.']);

    }




    public function deactivatePartnerStudents($partnerId)
    {
        $partner = Partner::find($partnerId);
        if (!$partner) {
            return response()->json(['message' => 'Partner not found'], 404);
        }
        $this->authorize('hold', $partner);

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
        $this->authorize('hold', $partner);

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
