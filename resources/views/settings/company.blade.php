@extends('layouts.app')
@section('page-title')
    {{__('Company Settings')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">{{__('Company Settings')}}</a>
        </li>
    </ul>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('setting.company') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="company_name" class="form-label">{{ __('Name') }}</label>
                            <input type="text" name="company_name" id="company_name" class="form-control" placeholder="{{ __('Enter company name') }}" value="{{ old('company_name', $settings['company_name']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="company_email" class="form-label">{{ __('Email') }}</label>
                            <input type="text" name="company_email" id="company_email" class="form-control" placeholder="{{ __('Enter company email') }}" value="{{ old('company_email', $settings['company_email']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="company_phone" class="form-label">{{ __('Phone Number') }}</label>
                            <input type="text" name="company_phone" id="company_phone" class="form-control" placeholder="{{ __('Enter company phone') }}" value="{{ old('company_phone', $settings['company_phone']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="company_address" class="form-label">{{ __('Address') }}</label>
                            <textarea name="company_address" id="company_address" class="form-control" rows="2">{{ old('company_address', $settings['company_address']) }}</textarea>
                        </div>
                        {{-- add IF,ICE,Patente,RC  --}}
                        <div class="form-group col-md-6">
                            <label for="patente" class="form-label">{{ __('Patente') }}</label>
                            <input type="text" name="patente" id="patente" class="form-control" placeholder="{{ __('Enter patente') }}" value="{{ old('patente', !empty($settings['patente']) ? $settings['patente'] : '') }}">
                        </div>

                        <div class="form-group col-md-6">
                            <label for="rc" class="form-label">{{ __('RC') }}</label>
                            <input type="text" name="rc" id="rc" class="form-control" placeholder="{{ __('Enter RC') }}" value="{{ old('rc', !empty($settings['rc']) ? $settings['rc'] : '') }}">
                        </div>

                        <div class="form-group col-md-6">
                            <label for="if" class="form-label">{{ __('IF') }}</label>
                            <input type="text" name="if" id="if" class="form-control" placeholder="{{ __('Enter IF') }}" value="{{ old('if', !empty($settings['if']) ? $settings['if'] : '') }}">
                        </div>

                        <div class="form-group col-md-6">
                            <label for="ice" class="form-label">{{ __('ICE') }}</label>
                            <input type="text" name="ice" id="ice" class="form-control" placeholder="{{ __('Enter ICE') }}" value="{{ old('ice', !empty($settings['ice']) ? $settings['ice'] : '') }}">
                        </div>

                        <div class="form-group col-md-6">
                            <label for="client_number_prefix" class="form-label">{{ __('Client Number Prefix') }}</label>
                            <input type="text" name="client_number_prefix" id="client_number_prefix" class="form-control" placeholder="{{ __('Enter client number prefix') }}" value="{{ old('client_number_prefix', $settings['client_number_prefix']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="driver_number_prefix" class="form-label">{{ __('Driver Number Prefix') }}</label>
                            <input type="text" name="driver_number_prefix" id="driver_number_prefix" class="form-control" placeholder="{{ __('Enter driver number prefix') }}" value="{{ old('driver_number_prefix', $settings['driver_number_prefix']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="vehicle_number_prefix" class="form-label">{{ __('Vehicle Number Prefix') }}</label>
                            <input type="text" name="vehicle_number_prefix" id="vehicle_number_prefix" class="form-control" placeholder="{{ __('Enter vehicle number prefix') }}" value="{{ old('vehicle_number_prefix', $settings['vehicle_number_prefix']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="booking_number_prefix" class="form-label">{{ __('Booking Number Prefix') }}</label>
                            <input type="text" name="booking_number_prefix" id="booking_number_prefix" class="form-control" placeholder="{{ __('Enter booking number prefix') }}" value="{{ old('booking_number_prefix', $settings['booking_number_prefix']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="rental_agreement_number_prefix" class="form-label">{{ __('Rental Agreement Number Prefix') }}</label>
                            <input type="text" name="rental_agreement_number_prefix" id="rental_agreement_number_prefix" class="form-control" placeholder="{{ __('Enter rental agreement number prefix') }}" value="{{ old('rental_agreement_number_prefix', $settings['rental_agreement_number_prefix']) }}">
                        </div>
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
                        <div class="form-group col-md-3">
                            <label class="form-label">{{ __('System Date Format') }}</label>
                            <div class="">
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="company_date_format1" name="company_date_format" class="custom-control-input" value="M j, Y" {{($settings['company_date_format'] =='M j, Y')?'checked':''}}>
                                    <label class="custom-control-label" for="company_date_format1">{{date('M d,Y')}}</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="company_date_format2" name="company_date_format" class="custom-control-input" value="y-m-d" {{($settings['company_date_format'] =='y-m-d')?'checked':''}}>
                                    <label class="custom-control-label" for="company_date_format2">{{date('y-m-d')}}</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="company_date_format3" name="company_date_format" class="custom-control-input" value="d-m-y" {{($settings['company_date_format'] =='d-m-y')?'checked':''}}>
                                    <label class="custom-control-label" for="company_date_format3">{{date('d-m-y')}}</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="company_date_format4" name="company_date_format" class="custom-control-input" value="m-d-y" {{($settings['company_date_format'] =='m-d-y')?'checked':''}}>
                                    <label class="custom-control-label" for="company_date_format4">{{date('m-d-y')}}</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="form-label">{{ __('System Time Format') }}</label>
                            <div class="">
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="company_time_format1" name="company_time_format" class="custom-control-input" value="H:i" {{($settings['company_time_format'] =='H:i')?'checked':''}}>
                                    <label class="custom-control-label" for="company_time_format1">{{date('H:i')}}</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="company_time_format2" name="company_time_format" class="custom-control-input" value="g:i A" {{($settings['company_time_format'] =='g:i A')?'checked':''}}>
                                    <label class="custom-control-label" for="company_time_format2">{{date('g:i A')}}</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="company_time_format3" name="company_time_format" class="custom-control-input" value="g:i a" {{($settings['company_time_format'] =='g:i a')?'checked':''}}>
                                    <label class="custom-control-label" for="company_time_format3">{{date('g:i a')}}</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-10">
                            <button type="submit" class="btn btn-primary btn-rounded">{{__('Save')}}</button>
                        </div>
                    </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
