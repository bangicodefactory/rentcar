<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Driver;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
// use Creagia\LaravelSignPad\Signature;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            $signatures = Signature::with('user')->orderBy('created_at', 'desc')->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('signature.index', compact('signatures'));
    }
    public function create(){    
        
        $users = User::where('id', parentId())->orderBy('created_at', 'desc')->get();
        

        $drivers = User::where('parent_id', parentId())
                   ->where('type', 'driver')
                   ->orderBy('created_at', 'desc')
                   ->get();
        $gender = User::$gender;

        return Inertia::render('Signature/Create', [
            'drivers' => $drivers->map(fn($d) => ['id' => $d->id, 'name' => $d->name]),
        ]);
    }
    public function store(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'signature' => 'required'
            ]);
    
            // Get the base64 image data
            $signature = $request->input('signature');
            
            // Verify if it's a valid base64 image
            if (strpos($signature, 'data:image/png;base64,') === false) {
                return redirect()->back()
                    ->with('error', 'Invalid signature format')
                    ->withInput();
            }
    
            // Clean the base64 string
            $signature = str_replace('data:image/png;base64,', '', $signature);
            $signature = str_replace(' ', '+', $signature);
            
            // Decode base64
            $imageData = base64_decode($signature);
    
            if (!$imageData) {
                return redirect()->back()
                    ->with('error', 'Failed to decode signature')
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
                    ->with('error', 'Failed to save signature')
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
                    ->with('error', 'Failed to save signature record')
                    ->withInput();
            }
    
            return redirect()->back()
                ->with('success', 'Signature saved successfully');
    
        } catch (\Exception $e) {
            \Log::error('Signature save error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'An error occurred while saving the signature')
                ->withInput();
        }
    }

    public function destroy(Signature $signature){
        if (\Auth::user()->can('delete driver')) {
            
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

}
