@extends('layouts.app')
@section('page-title')
    {{__('Payment Settings')}}
@endsection
@php
    $settings=settings();
@endphp
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">{{__('Payment Settings')}}</a>
        </li>
    </ul>
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('setting.payment') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="CURRENCY_SYMBOL" class="form-label">{{ __('Currency Icon') }}</label>
                            <input type="text" name="CURRENCY_SYMBOL" id="CURRENCY_SYMBOL" class="form-control" placeholder="{{ __('Enter currency icon') }}" value="{{ old('CURRENCY_SYMBOL', $settings['CURRENCY_SYMBOL']) }}" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="CURRENCY" class="form-label">{{ __('Currency Code') }}</label>
                            <input type="text" name="CURRENCY" id="CURRENCY" class="form-control font-style" placeholder="{{ __('Enter currency code') }}" value="{{ old('CURRENCY', $settings['CURRENCY']) }}" required>
                        </div>
                    </div>
                    <hr>

                    {{--------------------------Stripe Payment settings---------------------------------}}
                    <div class="row mt-2">
                        <div class="col-auto">
                            <label for="stripe_payment" class="form-label">{{ __('Stripe Payment') }}</label>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check custom-chek">
                                    <input class="form-check-input" type="checkbox" name="stripe_payment" id="stripe_payment" {{ $settings['STRIPE_PAYMENT'] == 'on' ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="stripe_key" class="form-label">{{ __('Account Key') }}</label>
                            <input type="text" name="stripe_key" id="stripe_key" class="form-control" placeholder="{{ __('Enter stripe key') }}" value="{{ old('stripe_key', $settings['STRIPE_KEY']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="stripe_secret" class="form-label">{{ __('Account Secret Key') }}</label>
                            <input type="text" name="stripe_secret" id="stripe_secret" class="form-control" placeholder="{{ __('Enter stripe secret') }}" value="{{ old('stripe_secret', $settings['STRIPE_SECRET']) }}">
                        </div>
                    </div>
                    <hr>
                    {{--------------------------Paypal Payment settings---------------------------------}}
                    <div class="row mt-2">
                        <div class="col-auto">
                            <label for="paypal_payment" class="form-label">{{ __('Paypal Payment') }}</label>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check custom-chek">
                                    <input class="form-check-input" type="checkbox" name="paypal_payment"
                                           id="paypal_payment" {{ $settings['paypal_payment'] == 'on' ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-12">
                            <label for="paypal_mode" class="form-label me-2">{{ __('Account Mode') }}</label>
                            <div class="form-check custom-chek form-check-inline">
                                <input class="form-check-input" type="radio" value="sandbox" id="sandbox" name="paypal_mode" {{ $settings['paypal_mode'] == 'sandbox' ? 'checked' : '' }}>
                                <label class="form-check-label" for="sandbox">{{__('Sandbox')}} </label>
                            </div>
                            <div class="form-check custom-chek form-check-inline">
                                <input class="form-check-input" type="radio" value="live" id="live"
                                       name="paypal_mode" {{ $settings['paypal_mode'] == 'live' ? 'checked' : '' }}>
                                <label class="form-check-label" for="live">{{__('Live')}} </label>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="paypal_client_id" class="form-label">{{ __('Account Client ID') }}</label>
                            <input type="text" name="paypal_client_id" id="paypal_client_id" class="form-control" placeholder="{{ __('Enter client id') }}" value="{{ old('paypal_client_id', $settings['paypal_client_id']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="paypal_secret_key" class="form-label">{{ __('Account Secret Key') }}</label>
                            <input type="text" name="paypal_secret_key" id="paypal_secret_key" class="form-control" placeholder="{{ __('Enter secret key') }}" value="{{ old('paypal_secret_key', $settings['paypal_secret_key']) }}">
                        </div>
                    </div>
                    <hr>
                    {{--------------------------Bank Transfer settings---------------------------------}}
                    <div class="row mt-2">
                        <div class="col-auto">
                            <label for="bank_transfer_payment" class="form-label">{{ __('Bank Transfer Payment') }}</label>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check custom-chek">
                                    <input class="form-check-input" type="checkbox" name="bank_transfer_payment" id="bank_transfer_payment" {{ $settings['bank_transfer_payment'] == 'on' ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="bank_name" class="form-label">{{ __('Bank Name') }}</label>
                            <input type="text" name="bank_name" id="bank_name" class="form-control" placeholder="{{ __('Enter bank name') }}" value="{{ old('bank_name', $settings['bank_name']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="bank_holder_name" class="form-label">{{ __('Bank Holder Name') }}</label>
                            <input type="text" name="bank_holder_name" id="bank_holder_name" class="form-control" placeholder="{{ __('Enter bank holder name') }}" value="{{ old('bank_holder_name', $settings['bank_holder_name']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="bank_account_number" class="form-label">{{ __('Bank Account Number') }}</label>
                            <input type="text" name="bank_account_number" id="bank_account_number" class="form-control" placeholder="{{ __('Enter bank account number') }}" value="{{ old('bank_account_number', $settings['bank_account_number']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="bank_ifsc_code" class="form-label">{{ __('Bank IFSC') }}</label>
                            <input type="text" name="bank_ifsc_code" id="bank_ifsc_code" class="form-control" placeholder="{{ __('Enter bank ifsc code') }}" value="{{ old('bank_ifsc_code', $settings['bank_ifsc_code']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="bank_other_details" class="form-label">{{ __('Other Details') }}</label>
                            <textarea name="bank_other_details" id="bank_other_details" class="form-control" rows="1" placeholder="{{ __('Enter bank other details') }}">{{ old('bank_other_details', $settings['bank_other_details']) }}</textarea>
                        </div>
                    </div>

                    <hr>
                    {{--------------------------Flutterwave Payment settings---------------------------------}}
                    <div class="row mt-2">
                        <div class="col-auto">
                            <label for="flutterwave_payment" class="form-label">{{ __('Flutterwave Payment') }}</label>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check custom-chek">
                                    <input class="form-check-input" type="checkbox" name="flutterwave_payment" id="flutterwave_payment" {{ $settings['flutterwave_payment'] == 'on' ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="flutterwave_public_key" class="form-label">{{ __('Flutterwave Public Key') }}</label>
                                <input type="text" name="flutterwave_public_key" id="flutterwave_public_key" class="form-control" placeholder="{{ __('Enter flutterwave public key') }}" value="{{ old('flutterwave_public_key', $settings['flutterwave_public_key']) }}">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="flutterwave_secret_key" class="form-label">{{ __('Flutterwave Secret Key') }}</label>
                                <input type="text" name="flutterwave_secret_key" id="flutterwave_secret_key" class="form-control" placeholder="{{ __('Enter flutterwave secret key') }}" value="{{ old('flutterwave_secret_key', $settings['flutterwave_secret_key']) }}">
                            </div>
                        </div>
                    </div>

                    <div class="text-right">
                        <button type="submit" class="btn btn-primary btn-rounded">{{__('Save')}}</button>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
