<?php

use App\Mail\Common;
use App\Mail\EmailVerification;
use App\Mail\TestMail;
use App\Models\Custom;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Place;
use App\Models\RentalAgreement;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

if (!function_exists('settingsKeys')) {
    function settingsKeys()
    {
        return $settingsKeys = [
            "app_name" => "",
            "theme_color" => "color1",
            "color_type" => "default",
            "own_color" => "",
            "own_color_code" => "",
            "sidebar_mode" => "light",
            "layout_direction" => "ltrmode",
            "layout_mode" => "lightmode",
            "company_logo" => "logo.png",
            "company_favicon" => "favicon.png",
            "landing_logo" => "landing_logo.png",
            "meta_seo_title" => "",
            "meta_seo_keyword" => "",
            "meta_seo_description" => "",
            "meta_seo_image" => "",
            "company_date_format" => "M j, Y",
            "company_time_format" => "g:i A",
            "company_name" => "",
            "company_phone" => "",
            "company_address" => "",
            "company_email" => "",
            "company_email_from_name" => "",
            "google_recaptcha" => "off",
            "recaptcha_key" => "",
            "recaptcha_secret" => "",
            "owner_email_verification" => "off",
            "landing_page" => "on",
            "register_page" => "on",
            'SERVER_DRIVER' => "",
            'SERVER_HOST' => "",
            'SERVER_PORT' => "",
            'SERVER_USERNAME' => "",
            'SERVER_PASSWORD' => "",
            'SERVER_ENCRYPTION' => "",
            'FROM_EMAIL' => "",
            'FROM_NAME' => "",
            "client_number_prefix" => "#CLI-000",
            "driver_number_prefix" => "#DRV-000",
            "vehicle_number_prefix" => "#VHC-000",
            "booking_number_prefix" => "#BOK-000",
            "rental_agreement_number_prefix" => "#RAG-000",
            'CURRENCY' => "MAD",
            'CURRENCY_SYMBOL' => "Dh",
            'STRIPE_PAYMENT' => "off",
            'STRIPE_KEY' => "",
            'STRIPE_SECRET' => "",
            "paypal_payment" => "off",
            "paypal_mode" => "",
            "paypal_client_id" => "",
            "paypal_secret_key" => "",
            "bank_transfer_payment" => "on",
            "bank_name" => "Test Bank",
            "bank_holder_name" => "Bank Holder Name",
            "bank_account_number" => "123456",
            "bank_ifsc_code" => "123456",
            "bank_other_details" => "",
            "timezone" => "Pacific/Tahiti",
            // "TAHITI_NUMBER" => "",
            "flutterwave_payment" => "off",
            "flutterwave_public_key" => "",
            "flutterwave_secret_key" => "",
        ];
    }
}

if (!function_exists('settings')) {
    function settings()
    {
        $settingData = DB::table('settings');
        if (\Auth::check()) {
            $userId = parentId();
            $settingData = $settingData->where('parent_id', $userId);
        } else {
            $settingData = $settingData->where('parent_id', 1);
        }
        $settingData = $settingData->get();
        $details = settingsKeys();

        foreach ($settingData as $row) {
            $details[$row->name] = $row->value;
        }

        config(
            [
                'captcha.secret' => $details['recaptcha_secret'],
                'captcha.sitekey' => $details['recaptcha_key'],
                'options' => [
                    'timeout' => 30,
                ],
            ]
        );

        return $details;
    }
}

if (!function_exists('subscriptionPaymentSettings')) {
    function subscriptionPaymentSettings()
    {
        $settingData = DB::table('settings')->where('type', 'payment')->where('parent_id', '=', 1)->get();
        $result = [
            'CURRENCY' => "MAD",
            'CURRENCY_SYMBOL' => "Dh",
            'STRIPE_PAYMENT' => "off",
            'STRIPE_KEY' => "",
            'STRIPE_SECRET' => "",
            "paypal_payment" => "off",
            "paypal_mode" => "",
            "paypal_client_id" => "",
            "paypal_secret_key" => "",
            "bank_transfer_payment" => "on",
            "bank_name" => "Test Bank",
            "bank_holder_name" => "Bank Holder Name",
            "bank_account_number" => "123456",
            "bank_ifsc_code" => "123456",
            "bank_other_details" => "",
            "flutterwave_payment" => "off",
            "flutterwave_public_key" => "",
            "flutterwave_secret_key" => "",
        ];

        foreach ($settingData as $setting) {
            $result[$setting->name] = $setting->value;
        }

        return $result;
    }
}

if (!function_exists('invoicePaymentSettings')) {
    function invoicePaymentSettings($id)
    {
        $settingData = DB::table('settings')->where('type', 'payment')->where('parent_id', $id)->get();
        $result = [
            'CURRENCY' => "MAD",
            'CURRENCY_SYMBOL' => "Dh",
            'STRIPE_PAYMENT' => "off",
            'STRIPE_KEY' => "",
            'STRIPE_SECRET' => "",
            "paypal_payment" => "off",
            "paypal_mode" => "",
            "paypal_client_id" => "",
            "paypal_secret_key" => "",
            "bank_transfer_payment" => "off",
            "bank_name" => "",
            "bank_holder_name" => "",
            "bank_account_number" => "",
            "bank_ifsc_code" => "",
            "bank_other_details" => "",
            "flutterwave_payment" => "off",
            "flutterwave_public_key" => "",
            "flutterwave_secret_key" => "",
        ];

        foreach ($settingData as $row) {
            $result[$row->name] = $row->value;
        }
        return $result;
    }
}


if (!function_exists('getSettingsValByName')) {
    function getSettingsValByName($key)
    {
        $setting = settings();
        if (!isset($setting[$key]) || empty($setting[$key])) {
            $setting[$key] = '';
        }

        return $setting[$key];
    }
}

if (!function_exists('settingDateFormat')) {
    function settingDateFormat($settings, $date)
    {
        return date($settings['company_date_format'], strtotime($date));
    }
}
if (!function_exists('settingPriceFormat')) {
    function settingPriceFormat($settings, $price)
    {
        // Achraf changes
        // return $settings['CURRENCY_SYMBOL'] . $price;

        return $price . ' ' . $settings['CURRENCY_SYMBOL'];

    }
}
if (!function_exists('settingTimeFormat')) {
    function settingTimeFormat($settings, $time)
    {
        return date($settings['company_time_format'], strtotime($time));
    }
}
if (!function_exists('dateFormat')) {
    function dateFormat($date)
    {
        $settings = settings();

        return date($settings['company_date_format'], strtotime($date));
    }
    // function to display also hour and minute
    function dateFormatHourMinute($date)
    {
        $settings = settings();

        return date(
            $settings['company_date_format'] . ' H:i',
            strtotime(datetime: $date)
        );
    }
}
if (!function_exists('timeFormat')) {
    function timeFormat($time)
    {
        $settings = settings();

        return date($settings['company_time_format'], strtotime($time));
    }
}
if (!function_exists('priceFormat')) {
    function priceFormat($price)
    {
        $settings = settings();
// Achraf changes
        // return $settings['CURRENCY_SYMBOL'] . $price;
        return $price . ' ' . $settings['CURRENCY_SYMBOL'];
    }
}
if (!function_exists('parentId')) {
    function parentId()
    {
        if (\Auth::user()->type == 'owner' || \Auth::user()->type == 'super admin') {
            return \Auth::user()->id;
        } else {
            return \Auth::user()->parent_id;
        }

    }
}
if (!function_exists('assignSubscription')) {
    function assignSubscription($id)
    {
        $subscription = Subscription::find($id);
        if ($subscription) {
            \Auth::user()->subscription = $subscription->id;
            if ($subscription->interval == 'Monthly') {
                \Auth::user()->subscription_expire_date = Carbon::now()->addMonths(1)->isoFormat('YYYY-MM-DD');
            } elseif ($subscription->interval == 'Quarterly') {
                \Auth::user()->subscription_expire_date = Carbon::now()->addMonths(3)->isoFormat('YYYY-MM-DD');
            } elseif ($subscription->interval == 'Yearly') {
                \Auth::user()->subscription_expire_date = Carbon::now()->addYears(1)->isoFormat('YYYY-MM-DD');
            } else {
                \Auth::user()->subscription_expire_date = null;
            }
            \Auth::user()->save();

            $users = User::where('parent_id', '=', parentId())->whereNotIn('type', ['driver'])->get();


            if ($subscription->user_limit == 0) {
                foreach ($users as $user) {
                    $user->is_active = 1;
                    $user->save();
                }
            } else {
                $userCount = 0;
                foreach ($users as $user) {
                    $userCount++;
                    if ($userCount <= $subscription->user_limit) {
                        $user->is_active = 1;
                        $user->save();
                    } else {
                        $user->is_active = 0;
                        $user->save();
                    }
                }
            }
        } else {
            return [
                'is_success' => false,
                'error' => 'Subscription is deleted.',
            ];
        }
    }
}
if (!function_exists('assignManuallySubscription')) {
    function assignManuallySubscription($id, $userId)
    {
        $owner = User::find($userId);
        $subscription = Subscription::find($id);
        if ($subscription) {
            $owner->subscription = $subscription->id;
            if ($subscription->interval == 'Monthly') {
                $owner->subscription_expire_date = Carbon::now()->addMonths(1)->isoFormat('YYYY-MM-DD');
            } elseif ($subscription->interval == 'Quarterly') {
                $owner->subscription_expire_date = Carbon::now()->addMonths(3)->isoFormat('YYYY-MM-DD');
            } elseif ($subscription->interval == 'Yearly') {
                $owner->subscription_expire_date = Carbon::now()->addYears(1)->isoFormat('YYYY-MM-DD');
            } else {
                $owner->subscription_expire_date = null;
            }
            $owner->save();


            $users = User::where('parent_id', '=', parentId())->whereNotIn('type', ['super admin', 'owner'])->get();


            if ($subscription->user_limit == 0) {
                foreach ($users as $user) {
                    $user->is_active = 1;
                    $user->save();
                }
            } else {
                $userCount = 0;
                foreach ($users as $user) {
                    $userCount++;
                    if ($userCount <= $subscription->user_limit) {
                        $user->is_active = 1;
                        $user->save();
                    } else {
                        $user->is_active = 0;
                        $user->save();
                    }
                }
            }
        } else {
            return [
                'is_success' => false,
                'error' => 'Subscription is deleted.',
            ];
        }
    }
}
if (!function_exists('smtpDetail')) {
    function smtpDetail($id)
    {
        $settings = emailSettings($id);

        $smtpDetail = config(
            [
                'mail.mailers.smtp.transport' => $settings['SERVER_DRIVER'],
                'mail.mailers.smtp.host' => $settings['SERVER_HOST'],
                'mail.mailers.smtp.port' => $settings['SERVER_PORT'],
                'mail.mailers.smtp.encryption' => $settings['SERVER_ENCRYPTION'],
                'mail.mailers.smtp.username' => $settings['SERVER_USERNAME'],
                'mail.mailers.smtp.password' => $settings['SERVER_PASSWORD'],
                'mail.from.address' => $settings['FROM_EMAIL'],
                'mail.from.name' => $settings['FROM_NAME'],
            ]
        );

        return $smtpDetail;
    }
}

if (!function_exists('rentalAgreementPrefix')) {
    function rentalAgreementPrefix()
    {
        $settings = settings();
        return $settings["rental_agreement_number_prefix"];
    }
}
if (!function_exists('driverPrefix')) {
    function driverPrefix()
    {
        $settings = settings();
        return $settings["driver_number_prefix"];
    }
}
if (!function_exists('vehiclePrefix')) {
    function vehiclePrefix()
    {
        $settings = settings();
        return $settings["vehicle_number_prefix"];
    }
}
if (!function_exists('bookingPrefix')) {
    function bookingPrefix()
    {
        $settings = settings();
        return $settings["booking_number_prefix"];
    }
}


if (!function_exists('timeCalculation')) {
    function timeCalculation($startDate, $startTime, $endDate, $endTime)
    {
        $startdate = $startDate . ' ' . $startTime;
        $enddate = $endDate . ' ' . $endTime;

        $startDateTime = new DateTime($startdate);
        $endDateTime = new DateTime($enddate);

        $interval = $startDateTime->diff($endDateTime);
        $totalHours = $interval->h + $interval->i / 60;

        return number_format($totalHours, 2);
    }
}

if (!function_exists('setup')) {
    function setup()
    {
        $setupPath = storage_path() . "/installed";
        return $setupPath;
    }
}

if (!function_exists('userLoggedHistory')) {
    function userLoggedHistory()
    {
        $serverip = $_SERVER['REMOTE_ADDR'];
        $data = @unserialize(file_get_contents('http://ip-api.com/php/' . $serverip));
        if (isset($data['status']) && $data['status'] == 'success') {
            $browser = new \WhichBrowser\Parser($_SERVER['HTTP_USER_AGENT']);
            if ($browser->device->type == 'bot') {
                return redirect()->intended(RouteServiceProvider::HOME);
            }
            $referrerData = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER']) : null;
            $data['browser'] = $browser->browser->name ?? null;
            $data['os'] = $browser->os->name ?? null;
            $data['language'] = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? mb_substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : null;
            $data['device'] = User::getDevice($_SERVER['HTTP_USER_AGENT']);
            $data['referrer_host'] = !empty($referrerData['host']);
            $data['referrer_path'] = !empty($referrerData['path']);
            $result = json_encode($data);
            $details = new \App\Models\LoggedHistory();
            $details->type = Auth::user()->type;
            $details->user_id = Auth::user()->id;
            $details->date = date('Y-m-d H:i:s');
            $details->Details = $result;
            $details->ip = $serverip;
            $details->parent_id = parentId();
            $details->save();
        }
    }

    if (!function_exists('vehicleRateCalculation')) {
        function vehicleRateCalculation($daily_rate, $startDateTime, $endDateTime)
        {
            $startDateTime = new DateTime($startDateTime);
            $endDateTime = new DateTime($endDateTime);

            $interval = $endDateTime->diff($startDateTime);

            $days = $interval->days;
            $hours = $interval->h;
            $minuts = $interval->i;

            if ($days > 0 && $hours > 0) {
                $considerDays = $days + 1;
                $totalRate = $considerDays * $daily_rate;
            } elseif ($days > 0 && $hours == 0 && $minuts >= 15) {
                $considerDays = $days + 1;
                $totalRate = $considerDays * $daily_rate;
            } elseif ($days > 0 && $hours == 0) {
                $considerDays = $days;
                $totalRate = $considerDays * $daily_rate;
            } else {
                $considerDays = 1;
                $totalRate = $considerDays * $daily_rate;
            }

            $data['considerDays'] = $considerDays;
            $data['totalDays'] = $days;
            $data['totalHours'] = $hours;
            $data['totalMinuts'] = $minuts;
            $data['totalRate'] = str_replace(',', '', number_format($totalRate, 0));

            return $data;
        }
    }

    if (!function_exists('addonsRateCalculation')) {
        function addonsRateCalculation($addonIds, $days)
        {
            $addons = Addon::whereIn('id', $addonIds)->get();
            $amount = 0;
            foreach ($addons as $addon) {
                if ($addon->billing_type == 'daily') {
                    $amount += $addon->price * $days;
                } else {
                    $amount += $addon->price;
                }
            }
            return str_replace(',', '', number_format($amount, 0));
        }
    }
    if (!function_exists('specificAddonCalculation')) {
        function specificAddonCalculation($addonIds, $days)
        {
            $addons = Addon::whereIn('id', $addonIds)->get();
            $addonCal = [];
            foreach ($addons as $addon) {
                if ($addon->billing_type == 'daily') {
                    $addonData['addon'] = $addon->name;
                    $addonData['final_price'] = priceFormat($addon->price * $days);
                } else {
                    $addonData['addon'] = $addon->name;
                    $addonData['final_price'] = priceFormat($addon->price);
                }
                $addonCal[] = $addonData;
            }
            return $addonCal;
        }
    }

    if (!function_exists('placesRateCalculation')) {
        function placesRateCalculation($placeId)
        {
            $place = Place::where('id', $placeId)->first();
            $amount = !empty($place) ? $place->price : 0;
            return str_replace(',', '', number_format($amount, 0));
        }
    }

    if (!function_exists('specificPlacesRateCalculation')) {
        function specificPlacesRateCalculation($placeId)
        {
            $place = Place::where('id', $placeId)->first();
            $placeData['place'] = $place->name;
            // $placeData['final_price'] = priceFormat($place->price);
            $placeData['final_price'] = $place->price;
            return $placeData;
        }
    }
}

if (!function_exists('defaultDriverCreate')) {
    function defaultDriverCreate($id)
    {

        $driverRoleData = [
            'name' => 'driver',
            'parent_id' => $id,
        ];
        $systemDriverRole = Role::create($driverRoleData);

        $systemDriverPermissions = [
            ['name' => 'manage contact'],
            ['name' => 'create contact'],
            ['name' => 'edit contact'],
            ['name' => 'delete contact'],
            ['name' => 'manage note'],
            ['name' => 'create note'],
            ['name' => 'edit note'],
            ['name' => 'delete note'],
        ];
        $systemDriverRole->givePermissionTo($systemDriverPermissions);
        return $systemDriverRole;
    }


    if (!function_exists('defultTemplate')) {
        function defultTemplate($id)
        {
            $templateData = [
                'user_create' =>
                [
                    'name' => 'New User',
                    'module' => 'user_create',
                    'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{new_user_name}', '{app_link}', '{username}', '{password}'],
                    'subject' => 'Welcome to {company_name}!',
                    'template' => '
                        <p><strong>Dear {new_user_name}</strong>,</p><p>&nbsp;</p><blockquote><p>Welcome to {company_name}! We are excited to have you on board and look forward to providing you with an exceptional experience.</p><p>We hope you enjoy your experience with us. If you have any feedback, feel free to share it with us.</p><p>&nbsp;</p><p>Your account details are as follows:</p><p><strong>App Link:</strong> <a href="{app_link}">{app_link}</a></p><p><strong>Username:</strong> {username}</p><p><strong>Password:</strong> {password}</p><p>&nbsp;</p><p>Thank you for choosing {company_name}!</p></blockquote><p>Best regards,</p><p>{company_name}</p><p>{company_email}</p>
                    ',
                ],
                'new_driver' => [
                    'name' => 'New Driver',
                    'module' => 'new_driver',
                    'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{new_driver_name}'],
                    'subject' => 'Welcome to {company_name}!',
                    'template' => '
                        <p><strong>Dear {new_driver_name},</strong></p><p>&nbsp;</p>
                        <blockquote>
                            <p>Welcome to {company_name}! We are excited to have you join our team of professional drivers. Together, we aim to provide exceptional transportation services to our customers while ensuring a rewarding experience for you.</p>
                            <p>Your role is vital to our success, and we are here to support you every step of the way. If you have any questions or need assistance, don\'t hesitate to reach out to us.</p>
                            <p>&nbsp;</p>
                            <p>Thank you for choosing {company_name}!</p>
                        </blockquote>
                        <p>Best regards,</p>
                        <p>The {company_name} Team</p>
                        <p>{company_email} | {company_phone_number}</p>
                    ',
                ],
                'new_booking' => [
                    'name' => 'New Booking',
                    'module' => 'new_booking',
                    'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{driver_name}', '{booking_date}', '{start_date}', '{end_date}', '{vehicle_name}', '{vehicle_type}', '{vehicle_model}', '{vehicle_plate_number}', '{pickup_address}', '{drop_address}', '{status}'],
                    'subject' => 'Your Booking Confirmation with {company_name}',
                    'template' => '
                        <p><strong>Dear {driver_name},</strong></p><p>&nbsp;</p>
                        <blockquote>
                            <p>We are pleased to inform you about a new booking assigned to you. Below are the booking details:</p>
                            <p><strong>Booking Date:</strong> {booking_date}</p>
                            <p><strong>Pickup Address:</strong> {pickup_address}</p>
                            <p><strong>Drop Address:</strong> {drop_address}</p>
                            <p>&nbsp;</p>
                            <p><strong>Trip Details:</strong></p>
                            <p><strong>Start Date:</strong> {start_date}</p>
                            <p><strong>End Date:</strong> {end_date}</p>
                            <p>&nbsp;</p>
                            <p><strong>Vehicle Information:</strong></p>
                            <p><strong>Vehicle Name:</strong> {vehicle_name}</p>
                            <p><strong>Vehicle Type:</strong> {vehicle_type}</p>
                            <p><strong>Vehicle Model:</strong> {vehicle_model}</p>
                            <p><strong>License Plate:</strong> {vehicle_plate_number}</p>
                            <p>&nbsp;</p>
                            <p><strong>Status:</strong> {status}</p>
                            <p>&nbsp;</p>
                            <p>If you have any questions or need assistance, feel free to contact us at {company_email} or call us at {company_phone_number}.</p>
                            <p>&nbsp;</p>
                            <p>Thank you for being an integral part of {company_name}!</p>
                        </blockquote>
                        <p>Best regards,</p>
                        <p>The {company_name} Team</p>
                        <p>{company_email} | {company_phone_number}</p>
                    ',
                ],
                'new_agreement' => [
                    'name' => 'New Agreement',
                    'module' => 'new_agreement',
                    'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{driver_name}', '{agreement_start_date}', '{agreement_end_date}', '{vehicle_name}', '{vehicle_type}', '{vehicle_model}', '{vehicle_plate_number}', '{terms_condition}', '{status}'],
                    'subject' => 'New Agreement with {company_name}',
                    'template' => '
                        <p><strong>Dear {driver_name},</strong></p><p>&nbsp;</p>
                        <blockquote>
                            <p>We are pleased to confirm your new agreement with {company_name}. Below are the details of the agreement:</p>
                            <p><strong>Agreement Period:</strong></p>
                            <p>Start Date: {agreement_start_date}</p>
                            <p>End Date: {agreement_end_date}</p>
                            <p>&nbsp;</p>
                            <p><strong>Vehicle Details:</strong></p>
                            <p>Vehicle Name: {vehicle_name}</p>
                            <p>Vehicle Type: {vehicle_type}</p>
                            <p>Vehicle Model: {vehicle_model}</p>
                            <p>License Plate: {vehicle_plate_number}</p>
                            <p>&nbsp;</p>
                            <p><strong>Status:</strong> {status}</p>
                            <p>&nbsp;</p>
                            <p><strong>Terms and Conditions:</strong></p>
                            <p>{terms_condition}</p>
                            <p>&nbsp;</p>
                            <p>If you have any questions or require clarification regarding the agreement, please feel free to contact us at {company_email} or {company_phone_number}.</p>
                            <p>&nbsp;</p>
                            <p>Thank you for being a part of {company_name}. We look forward to a successful collaboration!</p>
                        </blockquote>
                        <p>Best regards,</p>
                        <p>The {company_name} Team</p>
                        <p>{company_email} | {company_phone_number}</p>
                    ',
                ],
                'agreement_status' => [
                    'name' => 'Agreement Status',
                    'module' => 'agreement_status',
                    'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{driver_name}', '{agreement_start_date}', '{agreement_end_date}', '{vehicle_name}', '{vehicle_type}', '{vehicle_model}', '{vehicle_plate_number}', '{terms_condition}', '{status}'],
                    'subject' => 'Update on Your Agreement Status with {company_name}',
                    'template' => '
                        <p><strong>Dear {driver_name},</strong></p><p>&nbsp;</p>
                        <blockquote>
                            <p>We wanted to update you regarding the status of your agreement with {company_name}. Below are the current details:</p>
                            <p>&nbsp;</p>
                            <p><strong>Agreement Details:</strong></p>
                            <p><strong>Start Date:</strong> {agreement_start_date}</p>
                            <p><strong>End Date:</strong> {agreement_end_date}</p>
                            <p><strong>Vehicle Information:</strong></p>
                            <p>Vehicle Name: {vehicle_name}</p>
                            <p>Vehicle Type: {vehicle_type}</p>
                            <p>Vehicle Model: {vehicle_model}</p>
                            <p>License Plate: {vehicle_plate_number}</p>
                            <p>&nbsp;</p>
                            <p><strong>Current Status:</strong> {status}</p>
                            <p>&nbsp;</p>
                            <p><strong>Terms and Conditions:</strong></p>
                            <p>{terms_condition}</p>
                            <p>&nbsp;</p>
                            <p>If you have any questions or require further clarification about this update, feel free to contact us at {company_email} or {company_phone_number}. We are here to assist you.</p>
                            <p>&nbsp;</p>
                            <p>Thank you for being a part of {company_name}!</p>
                        </blockquote>
                        <p>Best regards,</p>
                        <p>The {company_name} Team</p>
                        <p>{company_email} | {company_phone_number}</p>
                    ',
                ],
                'booking_status' => [
                    'name' => 'Booking Status',
                    'module' => 'booking_status',
                    'short_code' => ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{driver_name}', '{booking_date}', '{start_date}', '{end_date}', '{vehicle_name}', '{vehicle_type}', '{vehicle_model}', '{vehicle_plate_number}', '{pickup_address}', '{drop_address}', '{status}'],
                    'subject' => 'Update on Your Booking Status with {company_name}',
                    'template' => '
                        <p><strong>Dear {driver_name},</strong></p><p>&nbsp;</p>
                        <blockquote>
                            <p>This is to inform you about a change in the status of one of your bookings. Please find the updated details below:</p>
                            <p>&nbsp;</p>
                            <p><strong>Booking Details:</strong></p>
                            <p><strong>Booking Date:</strong> {booking_date}</p>
                            <p><strong>Pickup Address:</strong> {pickup_address}</p>
                            <p><strong>Drop Address:</strong> {drop_address}</p>
                            <p>&nbsp;</p>
                            <p><strong>Trip Information:</strong></p>
                            <p><strong>Start Date:</strong> {start_date}</p>
                            <p><strong>End Date:</strong> {end_date}</p>
                            <p>&nbsp;</p>
                            <p><strong>Vehicle Details:</strong></p>
                            <p><strong>Vehicle Name:</strong> {vehicle_name}</p>
                            <p><strong>Vehicle Type:</strong> {vehicle_type}</p>
                            <p><strong>Vehicle Model:</strong> {vehicle_model}</p>
                            <p><strong>License Plate:</strong> {vehicle_plate_number}</p>
                            <p>&nbsp;</p>
                            <p><strong>Updated Status:</strong> {status}</p>
                            <p>&nbsp;</p>
                            <p>If you have any questions or need further assistance, please don\'t hesitate to contact us at {company_email} or {company_phone_number}.</p>
                            <p>&nbsp;</p>
                            <p>Thank you for your dedication and continued efforts with {company_name}!</p>
                        </blockquote>
                        <p>Best regards,</p>
                        <p>The {company_name} Team</p>
                        <p>{company_email} | {company_phone_number}</p>
                    ',
                ],
            ];

            // Store all created templates if needed
            $createdTemplates = [];

            foreach ($templateData as $key => $value) {
                $template = new Notification();
                $template->module = $value['module'];
                $template->name = $value['name'];
                $template->subject = $value['subject'];
                $template->message = $value['template'];
                $template->short_code = json_encode($value['short_code']);
                $template->enabled_email = 0;
                $template->parent_id = $id; // Associate with the provided ID
                $template->save();

                $createdTemplates[] = $template; // Collect all created templates
            }

            // Return all created templates if needed
            return $createdTemplates;
        }
    }






    if (!function_exists('sendEmail')) {
        function sendEmail($to, $datas)
        {
            $datas['settings'] = settings();
            try {
                emailSettings(parentId());
                Mail::to($to)->send(new TestMail($datas));
                return [
                    'status' => 'success',
                    'message' => __('Email successfully sent'),
                ];
            } catch (\Exception $e) {

                Log::info($e->getMessage());
                return [
                    'status' => 'error',
                    'message' => __('We noticed that the email settings have not been configured for this system. As a result, email-related functionalities may not work as expected. please add valide email smtp details first.')
                ];
            }
        }
    }


    if (!function_exists('commonEmailSend')) {
        function commonEmailSend($to, $datas)
        {
            $datas['settings'] = settings();
            try {
                if ($datas['module'] == 'owner_create') {
                    emailSettings(1);
                } else {
                    emailSettings(parentId());
                }
                Mail::to($to)->send(new Common($datas));
                return [
                    'status' => 'success',
                    'message' => __('Email successfully sent'),
                ];
            } catch (\Exception $e) {
                Log::info($e->getMessage());
                return [
                    'status' => 'error',
                    'message' => __('We noticed that the email settings have not been configured for this system. As a result, email-related functionalities may not work as expected. please add valide email smtp details first.')
                ];
            }
        }
    }


    if (!function_exists('emailSettings')) {
        function emailSettings($id)
        {
            $settingData = DB::table('settings')
                ->where('type', 'smtp')
                ->where('parent_id', $id)
                ->get();

            $result = [
                'FROM_EMAIL' => "",
                'FROM_NAME' => "",
                'SERVER_DRIVER' => "",
                'SERVER_HOST' => "",
                'SERVER_PORT' => "",
                'SERVER_USERNAME' => "",
                'SERVER_PASSWORD' => "",
                'SERVER_ENCRYPTION' => "",
            ];

            foreach ($settingData as $setting) {
                $result[$setting->name] = $setting->value;
            }

            // Apply settings dynamically
            config([
                'mail.default' => $result['SERVER_DRIVER'] ?? '',
                'mail.mailers.smtp.host' => $result['SERVER_HOST'] ?? '',
                'mail.mailers.smtp.port' => $result['SERVER_PORT'] ?? '',
                'mail.mailers.smtp.encryption' => $result['SERVER_ENCRYPTION'] ?? '',
                'mail.mailers.smtp.username' => $result['SERVER_USERNAME'] ?? '',
                'mail.mailers.smtp.password' => $result['SERVER_PASSWORD'] ?? '',
                'mail.from.name' => $result['FROM_NAME'] ?? '',
                'mail.from.address' => $result['FROM_EMAIL'] ?? '',
            ]);
            return $result;
        }
    }


    if (!function_exists('sendEmailVerification')) {
        function sendEmailVerification($to, $data)
        {
            $data['settings'] = emailSettings(1);
            try {
                Mail::to($to)->send(new EmailVerification($data));

                return [
                    'status' => 'success',
                    'message' => __('Email successfully sent'),
                ];
            } catch (\Exception $e) {
                Log::error('Email Sending Failed: ' . $e->getMessage());

                return [
                    'status' => 'error',
                    'message' => __('We noticed that the email settings have not been configured for this system. As a result, email-related functionalities may not work as expected. please contact the administrator to resolve this issue.')
                ];
                return redirect()->back()->with('error', __(''));
            }
        }
    }


    if (!function_exists('MessageReplace')) {
        function MessageReplace($notification, $id = 0)
        {
            $return['subject'] = $notification->subject;
            $return['message'] = $notification->message;
            if (!empty($notification->password)) {
                $notification['password'] = $notification->password;
            }
            $settings = settings();
            if (!empty($notification)) {
                $search = [];
                $replace = [];
                if ($notification->module == 'user_create') {
                    $user = User::find($id);
                    $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{new_user_name}', '{app_link}', '{username}', '{password}'];
                    $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'], $user->name, env('APP_URL'), $user->email, $notification['password']];
                }
                if ($notification->module == 'new_driver') {
                    $user = User::find($id);
                    $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{new_driver_name}'];
                    $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'], $user->name];
                }
                if ($notification->module == 'new_booking') {
                    $booking=Booking::where('id',$id)->where('parent_id',parentId())->first();
                    $vehicle= Vehicle::find($booking->vehicle);
                    $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{driver_name}', '{booking_date}', '{start_date}', '{end_date}', '{vehicle_name}', '{vehicle_type}', '{vehicle_model}', '{vehicle_plate_number}', '{pickup_address}', '{drop_address}', '{status}'];
                    $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'],$booking->drivers->name ,$booking->created_at,$booking->start_date,$booking->end_date,$vehicle->name,$vehicle->types->type,$vehicle->model,$vehicle->license_plate,$booking->pickupAddress->depo_address,$booking->dropOffAddress->depo_address,$booking->status];
                }
                if ($notification->module == 'booking_status') {
                    $booking=Booking::where('id',$id)->where('parent_id',parentId())->first();
                    $vehicle= Vehicle::find($booking->vehicle);
                    $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{driver_name}', '{booking_date}', '{start_date}', '{end_date}', '{vehicle_name}', '{vehicle_type}', '{vehicle_model}', '{vehicle_plate_number}', '{pickup_address}', '{drop_address}', '{status}'];
                    $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'],$booking->drivers->name ,$booking->created_at,$booking->start_date,$booking->end_date,$vehicle->name,$vehicle->types->type,$vehicle->model,$vehicle->license_plate,$booking->pickupAddress->depo_address,$booking->dropOffAddress->depo_address,$booking->status];
                }
                if ($notification->module == 'new_agreement') {
                    $agreement=RentalAgreement::where('id',$id)->where('parent_id',parentId())->first();
                    $vehicle= Vehicle::find($agreement->vehicle);
                    $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{driver_name}', '{agreement_start_date}', '{agreement_end_date}', '{vehicle_name}', '{vehicle_type}', '{vehicle_model}', '{vehicle_plate_number}', '{terms_condition}', '{status}'];
                    $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'],$agreement->drivers->name ,$agreement->rental_start_date,$agreement->rental_end_date,$vehicle->name,$vehicle->types->type,$vehicle->model,$vehicle->license_plate,$agreement->terms_condition,$agreement->status];
                }
                if ($notification->module == 'agreement_status') {
                    $agreement=RentalAgreement::where('id',$id)->where('parent_id',parentId())->first();
                    $vehicle= Vehicle::find($agreement->vehicle);
                    $search = ['{company_name}', '{company_email}', '{company_phone_number}', '{company_address}', '{company_currency}', '{driver_name}', '{agreement_start_date}', '{agreement_end_date}', '{vehicle_name}', '{vehicle_type}', '{vehicle_model}', '{vehicle_plate_number}', '{terms_condition}', '{status}'];
                    $replace = [$settings['company_name'], $settings['company_email'], $settings['company_phone'], $settings['company_address'], $settings['CURRENCY_SYMBOL'],$agreement->drivers->name ,$agreement->rental_start_date,$agreement->rental_end_date,$vehicle->name,$vehicle->types->type,$vehicle->model,$vehicle->license_plate,$agreement->terms_condition,$agreement->status];
                }

                $return['subject'] = str_replace($search, $replace, $notification->subject);
                $return['message'] = str_replace($search, $replace, $notification->message);
            }

            return $return;
        }
    }


   if (!function_exists('trans_custom')) {
    function trans_custom($key, $parameters = [], $locale = null)
    {
        $localeMap = [
            'ar' => 'arabic',
            'fr' => 'french',
            'en' => 'english'
        ];

        $currentLocale = $locale ?? app()->getLocale();
        $mappedLocale = $localeMap[$currentLocale] ?? $currentLocale;

        // Get translation file path
        $filePath = resource_path("lang/{$mappedLocale}.json");

        if (file_exists($filePath)) {
            $translations = json_decode(file_get_contents($filePath), true);

            if (isset($translations[$key])) {
                $translation = $translations[$key];

                // Replace parameters if any
                foreach ($parameters as $param => $value) {
                    $translation = str_replace(':' . $param, $value, $translation);
                }

                return $translation;
            }
        }

        // Fallback to key if translation not found
        return $key;
    }
}
}

if (!function_exists('feature')) {
    /**
     * Check whether a named feature flag is enabled for the active client.
     *
     * Resolution order (highest precedence first):
     *   1. FEATURE_* env var  (emergency override)
     *   2. config/clients/<client>.php features array
     *   3. config/clients/_default.php features array  (falls through to false)
     */
    function feature(string $name): bool
    {
        // .env override wins if explicitly set (not null).
        $envOverride = config("features.{$name}");
        if ($envOverride !== null) {
            return (bool) $envOverride;
        }

        return (bool) config("client.features.{$name}", false);
    }
}
