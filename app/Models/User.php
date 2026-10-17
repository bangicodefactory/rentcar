<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use Notifiable;


    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'phone_number',
        'profile',
        'lang',
        'parent_id',
        'is_active',
        'company_name',
        'city',
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected $casts = [
        'email_verified_at' => 'datetime',
    ];


    /**
     * first_name/last_name are deliberately NOT fillable: only DriverController
     * writes them, explicitly. UserController's super-admin branch does
     * fill($request->all()), which must not reach them.
     */
    protected static function booted(): void
    {
        // Every writer other than DriverController (staff/profile form, booking
        // import, super-admin fill) changes `name` alone. Drop the stored parts
        // then, so no reader of the columns ever sees a stale pair.
        static::saving(function (User $user) {
            if ($user->isDirty('name') && !$user->isDirty(['first_name', 'last_name'])) {
                $user->first_name = null;
                $user->last_name = null;
            }
        });
    }

    /**
     * [first, last] for the driver edit/show forms.
     *
     * Prefers the stored first_name/last_name while they still agree with
     * `name` (DriverController@store/update write all three together; the
     * saving hook above clears them otherwise). Rows without parts fall back to
     * splitting `name` on its first run of whitespace: first word = first
     * name, rest = last name. Unicode-aware so Excel-import names with tabs or
     * NBSPs split the same way; malformed UTF-8 degrades to a byte split.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function nameParts(): array
    {
        if ($this->first_name !== null && $this->last_name !== null
            && $this->first_name . ' ' . $this->last_name === (string) $this->name) {
            return [$this->first_name, $this->last_name];
        }

        $trimmed = preg_replace('/^\s+|\s+$/u', '', (string) $this->name);
        $parts = $trimmed === null ? false : preg_split('/\s+/u', $trimmed, 2, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            $parts = explode(' ', trim((string) $this->name), 2);
        }

        return [($parts[0] ?? '') !== '' ? $parts[0] : null, $parts[1] ?? null];
    }

    public function totalUser()
    {
        return User::whereNotIn('type', ['driver'])->where('parent_id', $this->id)->count();
    }


    public function totalDriver()
    {
        return User::where('type', 'driver')->where('parent_id', $this->id)->count();
    }


    public function roleWiseUserCount($role)
    {
        return User::where('type', $role)->where('parent_id', parentId())->count();
    }

    public static function getDevice($user)
    {
        $mobileType = '/(?:phone|windows\s+phone|ipod|blackberry|(?:android|bb\d+|meego|silk|googlebot) .+? mobile|palm|windows\s+ce|opera mini|avantgo|mobilesafari|docomo)/i';
        $tabletType = '/(?:ipad|playbook|(?:android|bb\d+|meego|silk)(?! .+? mobile))/i';
        if (preg_match_all($mobileType, $user)) {
            return 'mobile';
        } else {
            if (preg_match_all($tabletType, $user)) {
                return 'tablet';
            } else {
                return 'desktop';
            }

        }
    }


    public static $gender = [
        'Male' => 'Male',
        'Female' => 'Female',
    ];

    public function drivers()
    {
        return $this->hasOne('App\Models\Driver', 'user_id', 'id');
    }

    public static $systemModules = [
        'user',
        'driver',
        'vehicle',
        'inspection',
        'booking',
        'planning',
        'expense',
        'rental agreement',
        'logged history',
        'settings',
        'reminder',
        'tva',
    ];

    public function languageKeyword()
    {
        __('Daily');
        __('Total');
        __('Automatic');
        __('Manual');
        __('Essence');
        __('Diesel');
    }
}
