# Test Catalogue — rentcar

**Generated:** 2026-05-24
**Branch:** `feat/modernization`
**Phase:** Phase 0 — burn-down list for Phase 1 test backfill

> **How to use this file:**
> Each row is one endpoint. The **Covered** column starts at `✗`.
> When a Feature test covers the happy path **and** at least one failure
> path (auth denial, validation error, or 404), change it to `✓`.
> Phase 1 cannot begin until every `✗` is resolved or explicitly
> deferred with a reason.
>
> Permission strings come directly from `can()` checks in the
> controller. "none" means there is no permission gate (auth middleware
> alone, or fully public).

---

## Summary

| Domain                  | Endpoints | Covered |
| ----------------------- | --------- | ------- |
| Auth (Breeze)           | 14        | ✗       |
| Home / Dashboard        | 3         | ✗       |
| Users                   | 10        | ✗       |
| Subscriptions           | 9         | ✗       |
| Coupons                 | 9         | ✗       |
| Payments                | 6         | ✗       |
| Settings                | 22        | ✗       |
| Permissions             | 5         | ✗       |
| Roles                   | 6         | ✗       |
| Drivers                 | 8         | ✗       |
| Vehicle Types           | 7         | ✗       |
| Vehicles                | 9         | ✗       |
| Inspections             | 7         | ✗       |
| Inspection Types        | 7         | ✗       |
| Bookings                | 14        | ✗       |
| Booking Payments        | 3         | ✗       |
| Request Bookings        | 6         | ✗       |
| Expenses                | 7         | ✗       |
| Expense Types           | 7         | ✗       |
| Options                 | 7         | ✗       |
| Addons                  | 9         | ✗       |
| Places                  | 8         | ✗       |
| Rental Agreements       | 9         | ✗       |
| Notifications           | 7         | ✗       |
| Reminders               | 15        | ✗       |
| Reminder Types          | 7         | ✗       |
| TVA                     | 10        | ✗       |
| TVA Renumber            | 3         | ✗       |
| Credits                 | 9         | ✗       |
| Signatures              | 4         | ✗       |
| API                     | 1         | ✗       |
| **Total**               | **267**   | **0**   |

---

## 1. Auth (Breeze) — `routes/auth.php`

| ✓/✗ | Verb | Path | Route name | Controller@method | Middleware | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/register` | `register` | `RegisteredUserController@create` | guest | — | — |
| ✗ | POST | `/register` | — | `RegisteredUserController@store` | guest | name, email, password, password_confirmation | creates User, sends email verification if enabled |
| ✗ | GET | `/login` | `login` | `AuthenticatedSessionController@create` | guest | — | — |
| ✗ | POST | `/login` | — | `AuthenticatedSessionController@store` | guest | email, password | starts session, fires Login event |
| ✗ | POST | `/logout` | `logout` | `AuthenticatedSessionController@destroy` | auth | — | destroys session |
| ✗ | GET | `/forgot-password` | `password.request` | `PasswordResetLinkController@create` | guest | — | — |
| ✗ | POST | `/forgot-password` | `password.email` | `PasswordResetLinkController@store` | guest | email | sends password-reset email |
| ✗ | GET | `/reset-password/{token}` | `password.reset` | `NewPasswordController@create` | guest | — | — |
| ✗ | POST | `/reset-password` | `password.update` | `NewPasswordController@store` | guest | token, email, password, password_confirmation | resets password, deletes reset token |
| ✗ | GET | `/verify-email` | `verification.notice` | `EmailVerificationPromptController@__invoke` | auth | — | — |
| ✗ | GET | `/verify-email/{id}/{hash}` | `verification.verify` | `VerifyEmailController@__invoke` | auth, signed, throttle:6,1 | — | marks email verified |
| ✗ | POST | `/email/verification-notification` | `verification.send` | `EmailVerificationNotificationController@store` | auth, throttle:6,1 | — | sends verification email |
| ✗ | GET | `/confirm-password` | `password.confirm` | `ConfirmablePasswordController@show` | auth | — | — |
| ✗ | POST | `/confirm-password` | — | `ConfirmablePasswordController@store` | auth | password | marks password confirmed in session |

---

## 2. Home / Dashboard — `HomeController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ------------ |
| ✗ | GET | `/` | — | `HomeController@index` | none | — |
| ✗ | GET | `/home` | `home` | `HomeController@index` | none | — |
| ✗ | GET | `/dashboard` | `dashboard` | `HomeController@index` | none | — |

> `HomeController@organizationByMonth`, `paymentByMonth`, and
> `incomeExpenseByMonth` are private helpers — covered indirectly by
> the dashboard test.

---

## 3. Users — `UserController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/users` | `users.index` | `UserController@index` | `manage user` | — | — |
| ✗ | GET | `/users/create` | `users.create` | `UserController@create` | — | — | — |
| ✗ | POST | `/users` | `users.store` | `UserController@store` | `create user` | name, email, password, phone_number, role | creates User + assigns Role; sends mail via `commonEmailSend()` |
| ✗ | GET | `/users/{user}` | `users.show` | `UserController@show` | — | — | — |
| ✗ | GET | `/users/{user}/edit` | `users.edit` | `UserController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/users/{user}` | `users.update` | `UserController@update` | `edit user` | name, email, phone_number, role | syncs user roles |
| ✗ | DELETE | `/users/{user}` | `users.destroy` | `UserController@destroy` | `delete user` | — | deletes User |
| ✗ | GET | `/logged/history` | `logged.history` | `UserController@loggedHistory` | `manage logged history` | — | — |
| ✗ | GET | `/logged/{id}/history/show` | `logged.history.show` | `UserController@loggedHistoryShow` | `manage logged history` | — | — |
| ✗ | DELETE | `/logged/{id}/history` | `logged.history.destroy` | `UserController@loggedHistoryDestroy` | `delete logged history` | — | deletes LoggedHistory |

---

## 4. Subscriptions — `SubscriptionController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/subscriptions` | `subscriptions.index` | `SubscriptionController@index` | `manage pricing packages` | — | — |
| ✗ | GET | `/subscriptions/create` | `subscriptions.create` | `SubscriptionController@create` | — | — | — |
| ✗ | POST | `/subscriptions` | `subscriptions.store` | `SubscriptionController@store` | `create pricing packages` | name, price, description, user_limit, trial_days, features | creates Subscription |
| ✗ | GET | `/subscriptions/{subscription}` | `subscriptions.show` | `SubscriptionController@show` | — | — | — |
| ✗ | GET | `/subscriptions/{subscription}/edit` | `subscriptions.edit` | `SubscriptionController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/subscriptions/{subscription}` | `subscriptions.update` | `SubscriptionController@update` | `edit pricing packages` | name, price, description, user_limit, trial_days, features | updates Subscription |
| ✗ | DELETE | `/subscriptions/{subscription}` | `subscriptions.destroy` | `SubscriptionController@destroy` | `delete pricing packages` | — | deletes Subscription |
| ✗ | GET | `/subscription/transaction` | `subscription.transaction` | `SubscriptionController@transaction` | `manage pricing transation` | — | — |
| ✗ | POST | `/subscription/{id}/stripe/payment` | `subscription.stripe.payment` | `SubscriptionController@stripePayment` | `buy pricing packages` | subscription_id, coupon_code | Stripe charge, creates PackageTransaction, updates user subscription |

---

## 5. Coupons — `CouponController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/coupons` | `coupons.index` | `CouponController@index` | `manage coupon` | — | — |
| ✗ | GET | `/coupons/create` | `coupons.create` | `CouponController@create` | — | — | — |
| ✗ | POST | `/coupons` | `coupons.store` | `CouponController@store` | `create coupon` | name, type, rate, applicable_packages, code, valid_for, use_limit, status | creates Coupon |
| ✗ | GET | `/coupons/{coupon}` | `coupons.show` | `CouponController@show` | — | — | — |
| ✗ | GET | `/coupons/{coupon}/edit` | `coupons.edit` | `CouponController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/coupons/{coupon}` | `coupons.update` | `CouponController@update` | `edit coupon` | name, type, rate, applicable_packages, code, valid_for, use_limit, status | updates Coupon |
| ✗ | DELETE | `/coupons/{coupon}` | `coupons.destroy` | `CouponController@destroy` | `delete coupon` | — | deletes Coupon |
| ✗ | GET | `/coupons/history` | `coupons.history` | `CouponController@history` | `manage coupon history` | — | — |
| ✗ | DELETE | `/coupons/history/{id}/destroy` | `coupons.history.destroy` | `CouponController@historyDestroy` | `manage coupon history` | — | deletes CouponHistory |

---

## 6. Payments — `PaymentController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | POST | `/subscription/{id}/bank-transfer` | `subscription.bank.transfer` | `PaymentController@subscriptionBankTransfer` | none | subscription_id | — |
| ✗ | GET | `/subscription/{id}/bank-transfer/action/{status}` | `subscription.bank.transfer.action` | `PaymentController@subscriptionBankTransferAction` | none | receipt (file) | stores receipt file, creates PackageTransaction, updates subscription |
| ✗ | POST | `/subscription/{id}/paypal` | `subscription.paypal` | `PaymentController@subscriptionPaypal` | none | subscription_id | initiates PayPal checkout |
| ✗ | GET | `/subscription/{id}/paypal/{status}` | `subscription.paypal.status` | `PaymentController@subscriptionPaypalStatus` | none | — | updates subscription on PayPal callback |
| ✗ | POST | `/subscription/{id}/flutterwave` | `subscription.flutterwave` | `PaymentController@subscriptionFlutterwave` | none | subscription_id | initiates Flutterwave checkout |
| ✗ | GET | `/subscription/flutterwave/{id}/{txref}` | `subscription.flutterwave.status` | `PaymentController@subscriptionFlutterwaveStatus` | none | — | updates subscription on Flutterwave callback |

---

## 7. Settings — `SettingController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/settings/account` | `setting.account` | `SettingController@account` | none | — | — |
| ✗ | POST | `/settings/account` | `setting.account` | `SettingController@accountData` | none | name, email, profile picture | updates user account |
| ✗ | DELETE | `/settings/account/delete` | `setting.account.delete` | `SettingController@accountDelete` | none | — | deletes user + related records |
| ✗ | GET | `/settings/password` | `setting.password` | `SettingController@password` | none | — | — |
| ✗ | POST | `/settings/password` | `setting.password` | `SettingController@passwordData` | none | old_password, password, password_confirmation | updates password |
| ✗ | GET | `/settings/general` | `setting.general` | `SettingController@general` | none | — | — |
| ✗ | POST | `/settings/general` | `setting.general` | `SettingController@generalData` | none | company_name, company_address, company_email, company_phone | updates settings rows |
| ✗ | GET | `/settings/smtp` | `setting.smtp` | `SettingController@smtp` | none | — | — |
| ✗ | POST | `/settings/smtp` | `setting.smtp` | `SettingController@smtpData` | none | mail_driver, mail_host, mail_port, mail_username, mail_password, mail_from_address | updates settings rows |
| ✗ | GET | `/settings/smtp-test` | `setting.smtp.test` | `SettingController@smtpTest` | none | — | — |
| ✗ | POST | `/settings/smtp-test` | `setting.smtp.testing` | `SettingController@smtpTestMailSend` | none | test_email | sends test mail |
| ✗ | GET | `/settings/payment` | `setting.payment` | `SettingController@payment` | none | — | — |
| ✗ | POST | `/settings/payment` | `setting.payment` | `SettingController@paymentData` | none | payment_method, stripe_key, stripe_secret, paypal_mode, paypal_client_id, paypal_secret | updates settings rows |
| ✗ | GET | `/settings/company` | `setting.company` | `SettingController@company` | none | — | — |
| ✗ | POST | `/settings/company` | `setting.company` | `SettingController@companyData` | none | company_name, company_logo, company_phone, company_address, company_email | stores logo file, updates settings |
| ✗ | POST | `/theme/settings` | `theme.settings` | `SettingController@themeSettings` | none | theme_color | updates theme setting |
| ✗ | GET | `/settings/site-seo` | `setting.site.seo` | `SettingController@siteSEO` | none | — | — |
| ✗ | POST | `/settings/site-seo` | `setting.site.seo` | `SettingController@siteSEOData` | none | meta_title, meta_description, meta_keywords | updates settings rows |
| ✗ | GET | `/settings/google-recaptcha` | `setting.google.recaptcha` | `SettingController@googleRecaptcha` | none | — | — |
| ✗ | POST | `/settings/google-recaptcha` | `setting.google.recaptcha` | `SettingController@googleRecaptchaData` | none | google_recaptcha_enabled, recaptcha_site_key, recaptcha_secret_key | updates settings rows |
| ✗ | GET | `/language/{lang}` | `language.change` | `SettingController@languageChange` | none | lang (route param) | updates user language preference |
| ✗ | POST | `/settings/store-signature` | `AdminSignature.store` | `SettingController@storeSignature` | none | signature (base64) | stores signature file |

---

## 8. Permissions — `PermissionController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/permission` | `permission.index` | `PermissionController@index` | none | — | — |
| ✗ | GET | `/permission/create` | `permission.create` | `PermissionController@create` | none | — | — |
| ✗ | POST | `/permission` | `permission.store` | `PermissionController@store` | none | name, display_name, description | creates Permission |
| ✗ | GET | `/permission/{permission}/edit` | `permission.edit` | `PermissionController@edit` | none | — | — |
| ✗ | DELETE | `/permission/{permission}` | `permission.destroy` | `PermissionController@destroy` | none | — | deletes Permission |

---

## 9. Roles — `RoleController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/role` | `role.index` | `RoleController@index` | none | — | — |
| ✗ | GET | `/role/create` | `role.create` | `RoleController@create` | none | — | — |
| ✗ | POST | `/role` | `role.store` | `RoleController@store` | none | name, display_name, description, permissions[] | creates Role, syncs permissions |
| ✗ | GET | `/role/{role}` | `role.show` | `RoleController@show` | none | — | — |
| ✗ | GET | `/role/{role}/edit` | `role.edit` | `RoleController@edit` | none | — | — |
| ✗ | PUT/PATCH | `/role/{role}` | `role.update` | `RoleController@update` | none | name, display_name, description, permissions[] | updates Role, syncs permissions |

---

## 10. Drivers — `DriverController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/driver` | `driver.index` | `DriverController@index` | `manage driver` | — | — |
| ✗ | GET | `/driver/new/create` | `driver.new.create` | `DriverController@newCreate` | — | — | — |
| ✗ | GET | `/driver/create` | `driver.create` | `DriverController@create` | — | — | — |
| ✗ | POST | `/driver` | `driver.store` | `DriverController@store` | `create driver` | name, email, phone_number, address, license_number, license_expiry_date, document_file | creates User + Driver; stores document file; assigns role; sends mail |
| ✗ | GET | `/driver/{driver}` | `driver.show` | `DriverController@show` | — | — | — |
| ✗ | GET | `/driver/{driver}/edit` | `driver.edit` | `DriverController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/driver/{driver}` | `driver.update` | `DriverController@update` | `edit driver` | name, email, phone_number, address, license_number, license_expiry_date, document_file | stores document file, updates Driver |
| ✗ | DELETE | `/driver/{driver}` | `driver.destroy` | `DriverController@destroy` | `delete driver` | — | deletes Driver + User |

---

## 11. Vehicle Types — `VehicleTypeController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/vehicle-type` | `vehicle-type.index` | `VehicleTypeController@index` | `manage vehicle type` | — | — |
| ✗ | GET | `/vehicle-type/create` | `vehicle-type.create` | `VehicleTypeController@create` | — | — | — |
| ✗ | POST | `/vehicle-type` | `vehicle-type.store` | `VehicleTypeController@store` | `create vehicle type` | type, notes | creates VehicleType |
| ✗ | GET | `/vehicle-type/{vehicle-type}` | `vehicle-type.show` | `VehicleTypeController@show` | — | — | — |
| ✗ | GET | `/vehicle-type/{vehicle-type}/edit` | `vehicle-type.edit` | `VehicleTypeController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/vehicle-type/{vehicle-type}` | `vehicle-type.update` | `VehicleTypeController@update` | `edit vehicle type` | type, notes | updates VehicleType |
| ✗ | DELETE | `/vehicle-type/{vehicle-type}` | `vehicle-type.destroy` | `VehicleTypeController@destroy` | `delete client` | — | deletes VehicleType |

---

## 12. Vehicles — `VehicleController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/vehicle` | `vehicle.index` | `VehicleController@index` | `manage vehicle` | — | — |
| ✗ | GET | `/vehicle/create` | `vehicle.create` | `VehicleController@create` | — | — | — |
| ✗ | POST | `/vehicle` | `vehicle.store` | `VehicleController@store` | `create vehicle` | type, name, model, engine_type, engine_no, license_plate, registration_expiry_date, picture, daily_rate, year_of_first_immatriculation, gearbox, fuel_type, number_of_seats, kilometers, option, notes, document | stores picture + document files, creates Vehicle |
| ✗ | GET | `/vehicle/{vehicle}` | `vehicle.show` | `VehicleController@show` | — | — | — |
| ✗ | GET | `/vehicle/{vehicle}/edit` | `vehicle.edit` | `VehicleController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/vehicle/{vehicle}` | `vehicle.update` | `VehicleController@update` | `edit vehicle` | type, name, model, engine_type, engine_no, license_plate, registration_expiry_date, daily_rate, gearbox, fuel_type, number_of_seats, kilometers, option, notes, document | stores document file, updates Vehicle |
| ✗ | DELETE | `/vehicle/{vehicle}` | `vehicle.destroy` | `VehicleController@destroy` | `delete vehicle` | — | deletes Vehicle |
| ✗ | GET | `/vehicle/rate/calculation` | `vehicle.rate.calculation` | `VehicleController@getVehicleRateCalculation` | none | vehicle_id, start_date_time, end_date_time, addons, pickup_place, drop_off_place, daily_price | — |
| ✗ | GET | `/vehicle/available` | `available.vehicle` | `VehicleController@getAvailableVehicle` | none | start_date_time, end_date_time, booking_id | — |

---

## 13. Inspections — `InspectionController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/inspection` | `inspection.index` | `InspectionController@index` | `manage inspection` | — | — |
| ✗ | GET | `/inspection/create` | `inspection.create` | `InspectionController@create` | — | — | — |
| ✗ | POST | `/inspection` | `inspection.store` | `InspectionController@store` | `create inspection` | vehicle_id, start_odometer, end_odometer, exterior_condition, interior_condition, fuel_level, receipt | stores receipt file, creates Inspection |
| ✗ | GET | `/inspection/{inspection}` | `inspection.show` | `InspectionController@show` | — | — | — |
| ✗ | GET | `/inspection/{inspection}/edit` | `inspection.edit` | `InspectionController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/inspection/{inspection}` | `inspection.update` | `InspectionController@update` | `edit inspection` | vehicle_id, start_odometer, end_odometer, exterior_condition, interior_condition, fuel_level, receipt | stores receipt file, updates Inspection |
| ✗ | DELETE | `/inspection/{inspection}` | `inspection.destroy` | `InspectionController@destroy` | `delete inspection` | — | deletes Inspection |

---

## 14. Inspection Types — `InspectionTypeController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/inspection-type` | `inspection-type.index` | `InspectionTypeController@index` | `manage inspection type` | — | — |
| ✗ | GET | `/inspection-type/create` | `inspection-type.create` | `InspectionTypeController@create` | — | — | — |
| ✗ | POST | `/inspection-type` | `inspection-type.store` | `InspectionTypeController@store` | `create inspection type` | name, description | creates InspectionType |
| ✗ | GET | `/inspection-type/{inspection-type}` | `inspection-type.show` | `InspectionTypeController@show` | — | — | — |
| ✗ | GET | `/inspection-type/{inspection-type}/edit` | `inspection-type.edit` | `InspectionTypeController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/inspection-type/{inspection-type}` | `inspection-type.update` | `InspectionTypeController@update` | `edit inspection type` | name, description | updates InspectionType |
| ✗ | DELETE | `/inspection-type/{inspection-type}` | `inspection-type.destroy` | `InspectionTypeController@destroy` | `delete inspection type` | — | deletes InspectionType |

---

## 15. Bookings — `BookingController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/booking` | `booking.index` | `BookingController@index` | `manage booking` | — | — |
| ✗ | GET | `/booking/create` | `booking.create` | `BookingController@create` | — | — | — |
| ✗ | POST | `/booking` | `booking.store` | `BookingController@store` | `create booking` | vehicle, driver, start_date_time, end_date_time, pickup_address, drop_off_address, status, addon, notes | creates Booking + Tva; sends mail; creates Notification |
| ✗ | GET | `/booking/{booking}` | `booking.show` | `BookingController@show` | `show booking` | — | — |
| ✗ | GET | `/booking/{booking}/edit` | `booking.edit` | `BookingController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/booking/{booking}` | `booking.update` | `BookingController@update` | `edit booking` | vehicle, driver, start_date_time, end_date_time, pickup_address, drop_off_address, status, addon, notes | updates Booking + Tva |
| ✗ | DELETE | `/booking/{booking}` | `booking.destroy` | `BookingController@destroy` | `delete booking` | — | deletes Booking |
| ✗ | POST | `/booking/bulk-destroy` | `booking.bulk-destroy` | `BookingController@bulkDestroy` | `delete booking` | ids[] | deletes multiple Bookings |
| ✗ | GET | `/booking/template/download` | `booking.template` | `BookingController@downloadTemplate` | none | — | streams Excel file |
| ✗ | POST | `/booking/import` | `booking.import` | `BookingController@importExcel` | `create booking` | file (xlsx/xls/csv) | parses spreadsheet; auto-creates Drivers + Vehicles; creates Bookings |
| ✗ | GET | `/planning` | `planning` | `BookingController@planning` | none | — | — |
| ✗ | GET | `/test-planning` | `test.planning` | `BookingController@testPlanning` | none (debug) | — | — |

---

## 16. Booking Payments — `BookingController` (payment sub-resource)

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/booking/{id}/payment/create` | `booking.payment.create` | `BookingController@paymentCreate` | none | — | — |
| ✗ | POST | `/booking/{id}/payment/store` | `booking.payment.store` | `BookingController@paymentStore` | `create booking payment` | booking_id, amount, payment_date, payment_method | creates BookingPayment |
| ✗ | DELETE | `/booking/{id}/payment/{pid}/destroy` | `booking.payment.destroy` | `BookingController@paymentDestroy` | `delete booking payment` | — | deletes BookingPayment |

---

## 17. Request Bookings (client-facing) — `RequestBookingController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/car/{id}` | `client.details` | `RequestBookingController@showSimilarCars` | none (public) | — | — |
| ✗ | POST | `/booking_request` | `booking.store_request` | `RequestBookingController@storeBooking` | none (public) | vehicle_id, name, email, phone_number, pickup_address, drop_off_address, start_date, end_date, start_time, end_time, driver, notes, company_name, city | creates Guest + BookingRequest |
| ✗ | GET | `/booking_requests` | `booking_requests.index` | `RequestBookingController@index` | — | — | — |
| ✗ | GET | `/booking_requests/{booking_request}` | `booking_requests.show` | `RequestBookingController@show` | — | — | — |
| ✗ | POST | `/booking_requests/{id}/approve` | `booking_requests.approve` | `RequestBookingController@confirmBooking` | `create booking` | — | firstOrCreate User; creates Booking; assigns role; sends mail |
| ✗ | POST | `/booking_requests/{id}/refuse` | `booking_requests.refuse` | `RequestBookingController@refuseBooking` | `delete booking` | — | updates BookingRequest status |

---

## 18. Expenses — `ExpenseController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/expense` | `expense.index` | `ExpenseController@index` | `manage expense` | — | — |
| ✗ | GET | `/expense/create` | `expense.create` | `ExpenseController@create` | — | — | — |
| ✗ | POST | `/expense` | `expense.store` | `ExpenseController@store` | `create expense` | category, description, amount, expense_date, receipt | stores receipt file, creates Expense |
| ✗ | GET | `/expense/{expense}` | `expense.show` | `ExpenseController@show` | — | — | — |
| ✗ | GET | `/expense/{expense}/edit` | `expense.edit` | `ExpenseController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/expense/{expense}` | `expense.update` | `ExpenseController@update` | `edit expense` | category, description, amount, expense_date, receipt | stores receipt file, updates Expense |
| ✗ | DELETE | `/expense/{expense}` | `expense.destroy` | `ExpenseController@destroy` | `delete expense` | — | deletes Expense |

---

## 19. Expense Types — `ExpenseTypeController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/expense-type` | `expense-type.index` | `ExpenseTypeController@index` | `manage expense type` | — | — |
| ✗ | GET | `/expense-type/create` | `expense-type.create` | `ExpenseTypeController@create` | — | — | — |
| ✗ | POST | `/expense-type` | `expense-type.store` | `ExpenseTypeController@store` | `create expense type` | name, description | creates ExpenseType |
| ✗ | GET | `/expense-type/{expense-type}` | `expense-type.show` | `ExpenseTypeController@show` | — | — | — |
| ✗ | GET | `/expense-type/{expense-type}/edit` | `expense-type.edit` | `ExpenseTypeController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/expense-type/{expense-type}` | `expense-type.update` | `ExpenseTypeController@update` | `edit expense type` | name, description | updates ExpenseType |
| ✗ | DELETE | `/expense-type/{expense-type}` | `expense-type.destroy` | `ExpenseTypeController@destroy` | `delete expense type` | — | deletes ExpenseType |

---

## 20. Options — `OptionController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/option` | `option.index` | `OptionController@index` | `manage options` | — | — |
| ✗ | GET | `/option/create` | `option.create` | `OptionController@create` | — | — | — |
| ✗ | POST | `/option` | `option.store` | `OptionController@store` | `create options` | name, price | creates Option |
| ✗ | GET | `/option/{option}` | `option.show` | `OptionController@show` | — | — | — |
| ✗ | GET | `/option/{option}/edit` | `option.edit` | `OptionController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/option/{option}` | `option.update` | `OptionController@update` | `edit options` | name, price | updates Option |
| ✗ | DELETE | `/option/{option}` | `option.destroy` | `OptionController@destroy` | `delete options` | — | deletes Option |

---

## 21. Addons — `AddonController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/addon` | `addon.index` | `AddonController@index` | `manage addon` | — | — |
| ✗ | GET | `/addon/create` | `addon.create` | `AddonController@create` | — | — | — |
| ✗ | POST | `/addon` | `addon.store` | `AddonController@store` | `create addon` | name, price, billing_type | creates Addon |
| ✗ | GET | `/addon/{addon}` | `addon.show` | `AddonController@show` | — | — | — |
| ✗ | GET | `/addon/{addon}/edit` | `addon.edit` | `AddonController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/addon/{addon}` | `addon.update` | `AddonController@update` | `edit addon` | name, price, billing_type | updates Addon |
| ✗ | DELETE | `/addon/{addon}` | `addon.destroy` | `AddonController@destroy` | `delete addon` | — | deletes Addon |
| ✗ | GET | `/addon/rate/calculation` | `addon.rate.calculation` | `AddonController@getAddonRateCalculation` | none | addon_id, days | — |
| ✗ | GET | `/addon/rate/reduction` | `addon.rate.reduction` | `AddonController@getReductionRateCalculation` | none | reduction_id, days | — |

---

## 22. Places — `PlaceController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/place` | `place.index` | `PlaceController@index` | `manage place` | — | — |
| ✗ | GET | `/place/create` | `place.create` | `PlaceController@create` | — | — | — |
| ✗ | POST | `/place` | `place.store` | `PlaceController@store` | `create place` | name, city, island, price, depo_name, depo_address | creates Place |
| ✗ | GET | `/place/{place}` | `place.show` | `PlaceController@show` | — | — | — |
| ✗ | GET | `/place/{place}/edit` | `place.edit` | `PlaceController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/place/{place}` | `place.update` | `PlaceController@update` | `edit place` | name, city, island, price, depo_name, depo_address | updates Place |
| ✗ | DELETE | `/place/{place}` | `place.destroy` | `PlaceController@destroy` | `delete place` | — | deletes Place |
| ✗ | GET | `/place/rate/calculation` | `place.rate.calculation` | `PlaceController@getPlaceRateCalculation` | none | place_id | — |

---

## 23. Rental Agreements — `RentalAgreementController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/rental-agreement` | `rental-agreement.index` | `RentalAgreementController@index` | `manage rental agreement` | — | — |
| ✗ | GET | `/rental-agreement/create` | `rental-agreement.create` | `RentalAgreementController@create` | — | — | — |
| ✗ | POST | `/rental-agreement` | `rental-agreement.store` | `RentalAgreementController@store` | `create rental agreement` | booking_id, customer_name, terms, create_booking | creates RentalAgreement; optionally creates Booking; sends mail |
| ✗ | GET | `/rental-agreement/{rental-agreement}` | `rental-agreement.show` | `RentalAgreementController@show` | `show rental agreement` | — | renders PDF-ready view |
| ✗ | GET | `/rental-agreement/{rental-agreement}/edit` | `rental-agreement.edit` | `RentalAgreementController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/rental-agreement/{rental-agreement}` | `rental-agreement.update` | `RentalAgreementController@update` | `edit rental agreement` | booking_id, customer_name, terms | updates RentalAgreement |
| ✗ | DELETE | `/rental-agreement/{rental-agreement}` | `rental-agreement.destroy` | `RentalAgreementController@destroy` | `delete rental agreement` | — | deletes RentalAgreement |
| ✗ | GET | `/drivers/search` | `drivers.search` | `RentalAgreementController@searchDrivers` | none | search query | — |
| ✗ | GET | _(internal)_ | — | `RentalAgreementController@getUserSignature` | none | user_id | reads signature file from storage |

---

## 24. Notifications — `NotificationController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/notification` | `notification.index` | `NotificationController@index` | `manage notification` | — | — |
| ✗ | GET | `/notification/create` | `notification.create` | `NotificationController@create` | — | — | — |
| ✗ | POST | `/notification` | `notification.store` | `NotificationController@store` | `create notification` | module, subject, message, enabled_email | creates Notification |
| ✗ | GET | `/notification/{notification}` | `notification.show` | `NotificationController@show` | — | — | — |
| ✗ | GET | `/notification/{notification}/edit` | `notification.edit` | `NotificationController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/notification/{notification}` | `notification.update` | `NotificationController@update` | `edit notification` | module, subject, message, enabled_email | updates Notification |
| ✗ | DELETE | `/notification/{notification}` | `notification.destroy` | `NotificationController@destroy` | `delete notification` | — | deletes Notification |

---

## 25. Reminders — `ReminderController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/reminder` | `reminder.index` | `ReminderController@index` | `manage reminder` | — | — |
| ✗ | GET | `/reminder/create` | `reminder.create` | `ReminderController@create` | — | — | — |
| ✗ | POST | `/reminder` | `reminder.store` | `ReminderController@store` | `create reminder` | title, description, reminder_date, reminder_time, type, vehicle_id | creates Reminder |
| ✗ | GET | `/reminder/{reminder}` | `reminder.show` | `ReminderController@show` | — | — | — |
| ✗ | GET | `/reminder/{reminder}/edit` | `reminder.edit` | `ReminderController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/reminder/{reminder}` | `reminder.update` | `ReminderController@update` | `edit reminder` | title, description, reminder_date, reminder_time, type | updates Reminder |
| ✗ | DELETE | `/reminder/{reminder}` | `reminder.destroy` | `ReminderController@destroy` | `delete reminder` | — | deletes Reminder |
| ✗ | GET | `/reminder/dashboard/data` | `reminder.dashboard.data` | `ReminderController@getDashboardData` | none | — | — |
| ✗ | GET | `/reminder/urgent/list` | `reminder.urgent.list` | `ReminderController@getUrgentReminders` | none | — | — |
| ✗ | GET | `/reminder/vehicle/{vehicle}` | `reminder.vehicle` | `ReminderController@getVehicleReminders` | none | vehicle (route param) | — |
| ✗ | GET | `/reminder/statistics/data` | `reminder.statistics` | `ReminderController@getReminderStatistics` | none | — | — |
| ✗ | POST | `/reminder/{reminder}/complete` | `reminder.complete` | `ReminderController@markAsCompleted` | none | — | updates Reminder status |
| ✗ | POST | `/reminder/{reminder}/snooze` | `reminder.snooze` | `ReminderController@snoozeReminder` | none | snooze_days | updates Reminder date |
| ✗ | POST | `/reminder/update-statuses` | `reminder.update.statuses` | `ReminderController@updateReminderStatuses` | none | — | batch-updates Reminder statuses |
| ✗ | POST | `/reminder/create-recurring` | `reminder.create.recurring` | `ReminderController@createRecurringReminders` | none | — | creates recurring Reminder records |

---

## 26. Reminder Types — `ReminderTypeController`

> **Note:** The `Route::resource('reminder-type', ...)` block is commented
> out in `routes/web.php`. Routes are unreachable until uncommented.

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/reminder-type` | `reminder-type.index` | `ReminderTypeController@index` | `manage reminder` | — | — |
| ✗ | GET | `/reminder-type/create` | `reminder-type.create` | `ReminderTypeController@create` | — | — | — |
| ✗ | POST | `/reminder-type` | `reminder-type.store` | `ReminderTypeController@store` | `create reminder` | name, description | creates ReminderType |
| ✗ | GET | `/reminder-type/{reminder-type}` | `reminder-type.show` | `ReminderTypeController@show` | — | — | — |
| ✗ | GET | `/reminder-type/{reminder-type}/edit` | `reminder-type.edit` | `ReminderTypeController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/reminder-type/{reminder-type}` | `reminder-type.update` | `ReminderTypeController@update` | `edit reminder` | name, description | updates ReminderType |
| ✗ | DELETE | `/reminder-type/{reminder-type}` | `reminder-type.destroy` | `ReminderTypeController@destroy` | `delete reminder` | — | deletes ReminderType |

---

## 27. TVA — `TvaController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/tva` | `tva.index` | `TvaController@index` | `manage tva` | — | — |
| ✗ | GET | `/tva/{tva}` | `tva.show` | `TvaController@show` | — | — | — |
| ✗ | GET | `/tva/{tva}/edit` | `tva.edit` | `TvaController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/tva/{tva}` | `tva.update` | `TvaController@update` | `manage tva` | tva_number, amount, tva_rate, facture_date, payment_date | updates Tva |
| ✗ | DELETE | `/tva/{tva}` | `tva.destroy` | `TvaController@destroy` | `manage tva` | — | deletes Tva |
| ✗ | GET | `/tva-report` | `tva.report` | `TvaController@report` | `manage tva report` | — | — |
| ✗ | POST | `/tva/bulk-download` | `tva.bulk.download` | `TvaController@bulkDownload` | `manage tva` | ids[] | generates PDFs + ZIP archive, streams download |
| ✗ | POST | `/tva/generate` | `tva.generate` | `TvaController@generateMonthlyTva` | none | — | creates monthly Tva records |

---

## 28. TVA Renumber — `TvaRenumberController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/tva/renumber` | `tva.renumber.index` | `TvaRenumberController@index` | none | year | — |
| ✗ | GET | `/tva/renumber/preview` | `tva.renumber.preview` | `TvaRenumberController@previewJson` | none | year | — |
| ✗ | POST | `/tva/renumber/apply` | `tva.renumber.apply` | `TvaRenumberController@apply` | none | year | renumbers Tva records via TvaRenumberService |

---

## 29. Credits — `CreditController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/credit` | `credit.index` | `CreditController@index` | `manage driver` | — | — |
| ✗ | GET | `/credit/create` | `credit.create` | `CreditController@create` | — | — | — |
| ✗ | POST | `/credit` | `credit.store` | `CreditController@store` | `manage driver` | driver_id, amount, status, credit_date, notes | updates Driver credit balance, creates LoggedHistory |
| ✗ | GET | `/credit/{credit}` | `credit.show` | `CreditController@show` | `manage driver` | — | — |
| ✗ | GET | `/credit/{credit}/edit` | `credit.edit` | `CreditController@edit` | — | — | — |
| ✗ | PUT/PATCH | `/credit/{credit}` | `credit.update` | `CreditController@update` | `manage driver` | driver_id, amount, status, credit_date, notes | updates Driver credit balance, updates LoggedHistory |
| ✗ | DELETE | `/credit/{credit}` | `credit.destroy` | `CreditController@destroy` | none | — | deletes Credit |
| ✗ | GET | `/credit/search-drivers` | `credit.search.drivers` | `CreditController@searchDrivers` | none | search | — |
| ✗ | GET | `/credit/driver-credit/{driver_id}` | `credit.driver.details` | `CreditController@getDriverCredit` | `create rental agreement` | driver_id | — |

---

## 30. Signatures — `SignatureController`

| ✓/✗ | Verb | Path | Route name | Controller@method | Permission | Key inputs | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ---------- | ------------ |
| ✗ | GET | `/signature` | `signature.index` | `SignatureController@index` | `manage driver` | — | — |
| ✗ | GET | `/signature/create` | `signature.create` | `SignatureController@create` | — | — | — |
| ✗ | POST | `/signature-pad` | `signature.store` | `SignatureController@store` | `manage driver` | user_id, signature (base64 PNG) | stores signature file, creates Signature |
| ✗ | DELETE | `/signature/{signature}` | `signature.destroy` | `SignatureController@destroy` | `delete driver` | — | deletes Signature + file |

---

## 31. API — `routes/api.php`

| ✓/✗ | Verb | Path | Route name | Controller@method | Middleware | Side effects |
| --- | ---- | ---- | ---------- | ----------------- | ---------- | ------------ |
| ✗ | GET | `/api/user` | — | closure → `$request->user()` | auth:sanctum | — |

---

## Appendix: routes excluded from coverage requirement

These routes exist in `routes/web.php` but do not require feature-test
coverage in Phase 1:

| Path | Reason |
| ---- | ------ |
| `/landing`, `/contact`, `/search`, `/newsletter/subscribe` | Static client-facing views / stub closures — no domain logic |
| `/ui-test/*` | Development-only scaffolding; marked for removal before production |
| `/test-planning` | Debug endpoint — no auth, should be removed before production |
| `/email-verification/{token}` | Delegated to Breeze; covered by auth test suite |
