<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Driver;
use App\Models\DriverBlacklist;
use App\Models\Notification;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Creagia\LaravelSignPad\Signature;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        if (! \Auth::user()->can('manage driver')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        // Paginate server-side (the list previously loaded every driver — 1k+ rows
        // for a busy tenant) and move the search server-side so it spans all pages.
        $search = trim((string) $request->get('search', ''));

        $drivers = User::where('parent_id', parentId())
            ->where('type', 'driver')
            ->with('drivers')  // Eager load the driver profile (avoids per-row N+1)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhereHas('drivers', function ($d) use ($search) {
                            // Match license number and the *displayed* driver ID.
                            // The display is driverPrefix() . driver_id, so match
                            // that same concatenation to keep the old client-side
                            // filter's reach (e.g. searching "DRV-7" still works).
                            $d->where('license_number', 'like', "%{$search}%")
                                ->orWhereRaw('CONCAT(?, driver_id) LIKE ?', [driverPrefix(), "%{$search}%"]);
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        // Batch-load active blacklists for just this page's drivers (BAN-252).
        $blacklists = DriverBlacklist::activeFor(
            $drivers->getCollection()->pluck('id')->all(),
            parentId()
        );

        $payload = $drivers->through(function ($user) use ($blacklists) {
            $data = $user->toArray();
            $driver = $user->drivers;
            $data['driver_id_display'] = !empty($driver) ? driverPrefix() . $driver->driver_id : null;
            $data['license_number'] = !empty($driver) && !empty($driver->license_number) ? $driver->license_number : null;
            $data['issue_date_display'] = !empty($driver) && !empty($driver->issue_date) ? dateFormat($driver->issue_date) : null;
            $data['expiration_date_display'] = !empty($driver) && !empty($driver->expiration_date) ? dateFormat($driver->expiration_date) : null;
            $bl = $blacklists->get($user->id);
            $data['is_blacklisted'] = (bool) $bl;
            $data['blacklist_reason'] = $bl?->reason;
            return $data;
        });

        return Inertia::render('Driver/Index', [
            'drivers' => $payload,
            'filters' => ['search' => $search],
        ]);
    }


    public function newCreate()
    {
        $gender = User::$gender;

        return Inertia::render('Driver/Create', compact('gender'));
    }

    public function create()
    {
        $gender = User::$gender;

        return Inertia::render('Driver/Create', compact('gender'));
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create driver')) {

            if (empty($request->email)) {
                $firstName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $request->first_name));
                $lastName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $request->last_name));
                $randomString = substr(md5(uniqid()), 0, 6);
                $request->email = $firstName . $lastName . $randomString . '@gmail.com';

                // Make sure the generated email is unique
                while (\DB::table('users')->where('email', $request->email)->exists()) {
                    $randomString = substr(md5(uniqid()), 0, 6);
                    $request->email = $firstName . '.' . $lastName . '.' . $randomString . '@gmail.com';
                }

                $validator = \Validator::make(
                    $request->all(),
                    [
                        'first_name' => 'required',
                        'last_name' => 'required',
                        // 'email' => 'required|email|unique:users',
                        'phone_number' => 'required|numeric',
                        'gender' => 'required',
                        'birth_date' => 'required',
                        'address' => 'required',
                        'license_number' => 'required',
                        'issue_date' => 'required',
                        'expiration_date' => 'required',
                        // 'sign' => 'required',
                        // 'document' => 'required',
                        // 'license' => 'required',
                    ]
                );
            } else {
                $validator = \Validator::make(
                    $request->all(),
                    [
                        'first_name' => 'required',
                        'last_name' => 'required',
                        'email' => 'required|email|unique:users',
                        'phone_number' => 'required|numeric',
                        'gender' => 'required',
                        'birth_date' => 'required',
                        'address' => 'required',
                        'license_number' => 'required',
                        'issue_date' => 'required',
                        'expiration_date' => 'required',
                        // 'sign' => 'required',
                        // 'document' => 'required',
                        // 'license' => 'required',
                    ]
                );
            }

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                if (!$request->hasHeader('X-Inertia') && $request->ajax()) {
                    $response['status'] = false;
                    $response['data'] = $messages->first();
                    $responses = json_encode($response);
                    return $responses;
                } else {
                    return redirect()->back()->with('error', $messages->first());
                }
            }

            if (Carbon::now()->subYears(18)->format('Y-m-d') > $request->birth_date) {
                $driver = new Driver();
                $driver->birth_date = $request->birth_date;
            } else {
                $errorMessages = __('Driver age should not be 18 years old.');
                if (!$request->hasHeader('X-Inertia') && $request->ajax()) {
                    $response['status'] = false;
                    $response['data'] = $errorMessages;
                    $responsee = json_encode($response);
                    return $responsee;
                } else {
                    return redirect()->back()->with('error', $errorMessages);
                }
            }
            $userRole = Role::where('name', 'driver')->where('parent_id', parentId())->first();
            $user = new User();
            $user->name = $request->first_name . ' ' . $request->last_name;
            $user->email = !empty($request->email) ? $request->email : null;
            $user->phone_number = !empty($request->phone_number) ? $request->phone_number : null;
            $user->password = \Hash::make(123456);
            $user->type = $userRole->name;
            $user->profile = 'avatar.png';
            $user->lang = 'english';
            $user->parent_id = parentId();
            $user->save();
            $user->assignRole($userRole);


            if (!empty($user)) {

                $driver->driver_id = $this->driverNumber();
                $driver->user_id = $user->id;
                $driver->gender = $request->gender;
                $driver->age = !empty($request->age) ? $request->age : 0;
                $driver->address = !empty($request->address) ? $request->address : null;
                $driver->license_number = !empty($request->license_number) ? $request->license_number : null;
                $driver->issue_date = !empty($request->issue_date) ? $request->issue_date : null;
                $driver->expiration_date = !empty($request->expiration_date) ? $request->expiration_date : null;
                $driver->reference = !empty($request->reference) ? $request->reference : null;
                $driver->notes = !empty($request->notes) ? $request->notes : null;
                $driver->ICE_company = !empty($request->ICE_company) ? $request->ICE_company : null;
                $driver->parent_id = parentId();
// Save id document 
                if (!empty($request->document)) {
                    $documentFilenameWithExt = $request->file('document')->getClientOriginalName();
                    $documentFilename = pathinfo($documentFilenameWithExt, PATHINFO_FILENAME);
                    $documentExtension = $request->file('document')->getClientOriginalExtension();
                    $documentFileName = $documentFilename . '_' . time() . '.' . $documentExtension;

                    $directory = storage_path('upload/document');
                    $filePath = $directory . $documentFilenameWithExt;
                    if (!file_exists($directory)) {
                        mkdir($directory, 0777, true);
                    }
                    $request->file('document')->storeAs('upload/document/', $documentFileName, 'public');
                    $driver->document = $documentFileName;
                }

                if (!empty($request->document1)) {
                    $documentFilenameWithExt1 = $request->file('document1')->getClientOriginalName();
                    $documentFilename1 = pathinfo($documentFilenameWithExt1, PATHINFO_FILENAME);
                    $documentExtension1 = $request->file('document1')->getClientOriginalExtension();
                    $documentFileName1 = $documentFilename1 . '_' . time() . '.' . $documentExtension1;

                    $request->file('document1')->storeAs('upload/document/', $documentFileName1, 'public');
                    $driver->document_1 = $documentFileName1;
                }
// Save license document
                if (!empty($request->license)) {
                    $licenseFilenameWithExt = $request->file('license')->getClientOriginalName();
                    $licenseFilename = pathinfo($licenseFilenameWithExt, PATHINFO_FILENAME);
                    $licenseExtension = $request->file('license')->getClientOriginalExtension();
                    $licenseFileName = $licenseFilename . '_' . time() . '.' . $licenseExtension;

                    $request->file('license')->storeAs('upload/license/', $licenseFileName, 'public');
                    $driver->license = $licenseFileName;
                }
                if (!empty($request->license1)) {
                    $licenseFilenameWithExt1 = $request->file('license1')->getClientOriginalName();
                    $licenseFilename1 = pathinfo($licenseFilenameWithExt1, PATHINFO_FILENAME);
                    $licenseExtension1 = $request->file('license1')->getClientOriginalExtension();
                    $licenseFileName1 = $licenseFilename1 . '_' . time() . '.' . $licenseExtension1;

                    $request->file('license1')->storeAs('upload/license/', $licenseFileName1, 'public');
                    $driver->license_1 = $licenseFileName1;
                }

                $driver->save();
            }


            $module = 'new_driver';
            $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
            $setting = settings();
            $errorMessage = '';
            if (!empty($notification) && $notification->enabled_email == 1) {
                $notification_responce = MessageReplace($notification, $user->id);
                $data['subject'] = $notification_responce['subject'];
                $data['message'] = $notification_responce['message'];
                $data['module'] = $module;
                $data['logo'] = $setting['company_logo'];
                $to = $user->email;

                $response = commonEmailSend($to, $data);
                if ($response['status'] == 'error') {
                    $errorMessage = $response['message'];
                }
            }

            if (isset($request->direct_create)) {
                if (!empty($driver)) {
                    $driverList = User::where('type', 'driver')->where('parent_id', parentId())
                        ->orderBy('created_at', 'desc') // newest driver first (unified across pickers)
                        ->orderBy('id', 'desc')         // tie-break: imported drivers share a created_at
                        ->get()
                        ->pluck('name', 'id');

                    $response['status'] = true;
                    $response['message'] = __('Driver successfully created');
                    $response['data'] = $driverList;
                } else {
                    $response['status'] = false;
                    $response['message'] = __('Driver created failed');
                    $response['data'] = $errorMessage;
                }

                return json_encode($response);
            } else {
                return redirect()->route('driver.index')->with('success', __('Driver successfully created.') . '</br>' . $errorMessage);
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function show($id)
    {
        if (!\Auth::user()->can('manage driver')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $user = $this->resolveTenantDriver($id);
        if (!$user) {
            return redirect()->route('driver.index')->with('error', __('Permission Denied.'));
        }
        $name = explode(' ', $user->name);
        $user->first_name = isset($name[0]) ? $name[0] : null;
        $user->last_name = isset($name[1]) ? $name[1] : null;
        $driver = $user->drivers;

        $driverPayload = null;
        if (!empty($driver)) {
            $driverPayload = $driver->toArray();
            $driverPayload['driver_id_display'] = driverPrefix() . $driver->driver_id;
            $driverPayload['birth_date_display'] = !empty($driver->birth_date) ? dateFormat($driver->birth_date) : null;
            $driverPayload['issue_date_display'] = !empty($driver->issue_date) ? dateFormat($driver->issue_date) : null;
            $driverPayload['expiration_date_display'] = !empty($driver->expiration_date) ? dateFormat($driver->expiration_date) : null;
        }
        // Blacklist status for the badge + action (BAN-252).
        $blacklist = DriverBlacklist::where('parent_id', parentId())
            ->where('driver_user_id', $user->id)
            ->whereNull('lifted_at')
            ->first();

        return Inertia::render('Driver/Show', [
            'driver' => $driverPayload,
            'user' => $user->toArray(),
            'is_blacklisted'   => (bool) $blacklist,
            'blacklist_reason' => $blacklist?->reason,
            'blacklisted_at'   => $blacklist ? dateFormat($blacklist->created_at) : null,
        ]);
    }

    /**
     * Blacklist a driver with a reason (BAN-252). {user} is the driver's user id.
     */
    public function blacklist(Request $request, $user)
    {
        if (! \Auth::user()->can('manage driver blacklist')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $request->validate(['reason' => 'required|string|max:2000']);

        // Tenant guard: only this tenant's drivers.
        $driverUser = User::where('id', $user)
            ->where('parent_id', parentId())
            ->where('type', 'driver')
            ->first();
        if (! $driverUser) {
            return redirect()->back()->with('error', __('Driver not found.'));
        }

        // Idempotent: don't stack active rows for the same driver.
        $exists = DriverBlacklist::where('parent_id', parentId())
            ->where('driver_user_id', $driverUser->id)
            ->whereNull('lifted_at')
            ->exists();
        if ($exists) {
            return redirect()->back()->with('error', __('Driver is already blacklisted.'));
        }

        DriverBlacklist::create([
            'driver_user_id' => $driverUser->id,
            'parent_id'      => parentId(),
            'reason'         => $request->reason,
            'blacklisted_by' => \Auth::id(),
        ]);

        return redirect()->back()->with('success', __('Driver blacklisted.'));
    }

    /**
     * Lift a driver's blacklist (BAN-252). Keeps the row for history.
     */
    public function unblacklist(Request $request, $user)
    {
        if (! \Auth::user()->can('manage driver blacklist')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $blacklist = DriverBlacklist::where('parent_id', parentId())
            ->where('driver_user_id', $user)
            ->whereNull('lifted_at')
            ->first();

        if (! $blacklist) {
            return redirect()->back()->with('error', __('Driver is not blacklisted.'));
        }

        $blacklist->update(['lifted_at' => now(), 'lifted_by' => \Auth::id()]);

        return redirect()->back()->with('success', __('Driver removed from blacklist.'));
    }


    public function edit($id)
    {
        if (!\Auth::user()->can('edit driver')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $user = $this->resolveTenantDriver($id);
        if (!$user) {
            return redirect()->route('driver.index')->with('error', __('Permission Denied.'));
        }
        $name = explode(' ', $user->name);
        $user->first_name = isset($name[0]) ? $name[0] : null;
        $user->last_name = isset($name[1]) ? $name[1] : null;
        $driver = $user->drivers;
        $gender = User::$gender;

        return Inertia::render('Driver/Edit', [
            'driver' => !empty($driver) ? $driver->toArray() : null,
            'user' => $user->toArray(),
            'gender' => $gender,
        ]);
    }


    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit driver')) {
            $user = $this->resolveTenantDriver($id);
            if (!$user) {
                return redirect()->route('driver.index')->with('error', __('Permission Denied.'));
            }

            $validator = \Validator::make(
                $request->all(),
                [
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'email' => 'required|email|unique:users,email,' . $user->id,

                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $user->name = $request->first_name . ' ' . $request->last_name;
            $user->email = $request->email;
            $user->phone_number = !empty($request->phone_number) ? $request->phone_number : null;
            $user->save();

            $driver = Driver::where('user_id', $user->id)->first();
            if ($driver) {
                $driver->gender = $request->gender;
                $driver->age = !empty($request->age) ? $request->age : 0;
                $driver->birth_date = !empty($request->birth_date) ? $request->birth_date : null;
                $driver->address = !empty($request->address) ? $request->address : null;
                $driver->license_number = !empty($request->license_number) ? $request->license_number : null;
                $driver->issue_date = !empty($request->issue_date) ? $request->issue_date : null;
                $driver->expiration_date = !empty($request->expiration_date) ? $request->expiration_date : null;
                $driver->reference = !empty($request->reference) ? $request->reference : null;
                $driver->notes = !empty($request->notes) ? $request->notes : null;
                $driver->ICE_company = !empty($request->ICE_company) ? $request->ICE_company : null;
                if (!empty($request->document)) {
                    $documentFilenameWithExt = $request->file('document')->getClientOriginalName();
                    $documentFilename = pathinfo($documentFilenameWithExt, PATHINFO_FILENAME);
                    $documentExtension = $request->file('document')->getClientOriginalExtension();
                    $documentFileName = $documentFilename . '_' . time() . '.' . $documentExtension;

                    $directory = storage_path('upload/document');
                    $filePath = $directory . $documentFilenameWithExt;


                    if (!file_exists($directory)) {
                        mkdir($directory, 0777, true);
                    }
                    $request->file('document')->storeAs('upload/document/', $documentFileName, 'public');
                    $driver->document = $documentFileName;
                }
                if (!empty($request->license)) {
                    $licenseFilenameWithExt = $request->file('license')->getClientOriginalName();
                    $licenseFilename = pathinfo($licenseFilenameWithExt, PATHINFO_FILENAME);
                    $licenseExtension = $request->file('license')->getClientOriginalExtension();
                    $licenseFileName = $licenseFilename . '_' . time() . '.' . $licenseExtension;

                    $request->file('license')->storeAs('upload/license/', $licenseFileName, 'public');
                    $driver->license = $licenseFileName;
                }
                if (!empty($request->license1)) {
                    $licenseFilenameWithExt1 = $request->file('license1')->getClientOriginalName();
                    $licenseFilename1 = pathinfo($licenseFilenameWithExt1, PATHINFO_FILENAME);
                    $licenseExtension1 = $request->file('license1')->getClientOriginalExtension();
                    $licenseFileName1 = $licenseFilename1 . '_' . time() . '.' . $licenseExtension1;

                    $request->file('license1')->storeAs('upload/license/', $licenseFileName1, 'public');
                    $driver->license_1 = $licenseFileName1;
                }
                if (!empty($request->document1)) {
                    $documentFilenameWithExt1 = $request->file('document1')->getClientOriginalName();
                    $documentFilename1 = pathinfo($documentFilenameWithExt1, PATHINFO_FILENAME);
                    $documentExtension1 = $request->file('document1')->getClientOriginalExtension();
                    $documentFileName1 = $documentFilename1 . '_' . time() . '.' . $documentExtension1;

                    $request->file('document1')->storeAs('upload/document/', $documentFileName1, 'public');
                    $driver->document_1 = $documentFileName1;
                }
                $driver->save();
            }
            return redirect()->route('driver.index')->with('success', __('Driver successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy($id)
    {
        if (\Auth::user()->can('delete driver')) {
            $user = $this->resolveTenantDriver($id);
            if (!$user) {
                return redirect()->route('driver.index')->with('error', __('Permission Denied.'));
            }
            $user->delete();
            Driver::where('user_id', $user->id)->delete();

            return redirect()->route('driver.index')->with('success', __('Client successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function driverNumber()
    {
        $max = Driver::where('parent_id', parentId())->max('driver_id');
        return ($max ?? 0) + 1;
    }
    /**
     * Resolve a driver the current user may act on: same tenant
     * (parent_id == parentId()) AND type = 'driver'. The super admin is
     * exempt — parentId() returns the SA's own id, which is never a driver's
     * parent_id, so a plain parent scope would lock the SA out of every
     * record. A non-owner with no resolvable tenant matches nothing.
     * Mirrors the guard blacklist() already applies.
     */
    private function resolveTenantDriver($id): ?User
    {
        $query = User::where('id', $id)->where('type', 'driver');

        if (\Auth::user()->type !== 'super admin') {
            $parentId = (int) parentId();
            if ($parentId <= 0) {
                return null;
            }
            $query->where('parent_id', $parentId);
        }

        return $query->first();
    }
}
