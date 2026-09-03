<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Driver;
use App\Models\Notification;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
// use Creagia\LaravelSignPad\Signature;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Signature;
use Inertia\Inertia;


class SignatureController extends Controller
{
    public function index(){
        if (\Auth::user()->can('manage driver')) {
            // $drivers = User::where('parent_id', parentId())
            // ->where('type', 'driver')
            // ->with('drivers')  // Eager load the drivers relationship
            // ->orderBy('created_at', 'desc')
            // ->get();
            $signatures = Signature::with('user')
                ->whereHas('user', fn ($q) => $this->scopeToTenant($q))
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return Inertia::render('Signature/Index', [
            'signatures' => $signatures->map(fn($s) => [
                'id'            => $s->id,
                'driver_name'   => $s->user?->name,
                'signature_url' => $s->signature_url ?? null,
                'created_at'    => $s->created_at?->format('Y-m-d'),
            ]),
        ]);
    }
    public function create(){    
        
        $users = User::where('id', parentId())->orderBy('created_at', 'desc')->get();
        

        $drivers = User::where('parent_id', parentId())
                   ->where('type', 'driver')
                   ->orderBy('created_at', 'desc') // newest driver first (unified across pickers)
                   ->orderBy('id', 'desc')         // tie-break: imported drivers share a created_at
                   ->get();
        $gender = User::$gender;

        return Inertia::render('Signature/Create', [
            'drivers' => $drivers->map(fn($d) => ['id' => $d->id, 'name' => $d->name]),
        ]);
    }
    public function store(Request $request)
    {
        if (!\Auth::user()->can('manage driver')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        try {
            // Validate request
            $request->validate([
                'user_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $this->scopeToTenant($q))],
                'signature' => 'required'
            ]);
    
            // Get the base64 image data
            $signature = $request->input('signature');
            
            // Verify if it's a valid base64 image
            if (strpos($signature, 'data:image/png;base64,') === false) {
                return redirect()->back()
                    ->with('error', __('Invalid signature format'))
                    ->withInput();
            }
    
            // Clean the base64 string
            $signature = str_replace('data:image/png;base64,', '', $signature);
            $signature = str_replace(' ', '+', $signature);
            
            // Decode base64
            $imageData = base64_decode($signature);
    
            if (!$imageData) {
                return redirect()->back()
                    ->with('error', __('Failed to decode signature'))
                    ->withInput();
            }
    
            // Create directory if it doesn't exist
            $directory = 'upload/signatures';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }
    
            // Generate unique filename
            $filename = 'signature_' . $request->user_id . '_' . time() . '.png';
            $fullPath = $directory . '/' . $filename;
    
            // Try to store the file
            if (!Storage::disk('public')->put($fullPath, $imageData)) {
                return redirect()->back()
                    ->with('error', __('Failed to save signature'))
                    ->withInput();
            }
    
            // Create signature record in database
            $signature = Signature::create([
                'user_id' => $request->user_id,
                'signature_path' => $fullPath
            ]);
    
            if (!$signature) {
                // If database insertion fails, delete the stored file
                Storage::disk('public')->delete($fullPath);
                return redirect()->back()
                    ->with('error', __('Failed to save signature record'))
                    ->withInput();
            }
    
            return redirect()->route('signature.index')
                ->with('success', __('Signature saved successfully'));
    
        } catch (\Exception $e) {
            \Log::error('Signature save error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', __('An error occurred while saving the signature'))
                ->withInput();
        }
    }

    public function destroy(Signature $signature){
        if (\Auth::user()->can('delete driver')) {
            if (!$signature->user || !$this->belongsToTenant($signature->user)) {
                return redirect()->route('signature.index')->with('error', __('Permission Denied.'));
            }
            
            \Log::info('Signature Path: ' . $signature->signature_path);
            \Log::info('Signature ID: ' . $signature->id);

            if($signature){
                \Log::info('Signature Existes'); 
                \Log::info('Signature Values:' . $signature); 
            }
            
            $signature->delete();
            
            return redirect()->route('signature.index')->with('success', __('Signature successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Signatures have no parent_id; the tenant is the signature's user —
     * the owner (id == parentId()) or a user whose parent_id is the owner.
     * Works on both Eloquent and query builders (whereHas / Rule::exists).
     */
    private function scopeToTenant($query)
    {
        $parentId = parentId();

        return $query->where(fn ($q) => $q->where('id', $parentId)->orWhere('parent_id', $parentId));
    }

    private function belongsToTenant(User $user): bool
    {
        $parentId = parentId();

        return $user->id == $parentId || $user->parent_id == $parentId;
    }

}
