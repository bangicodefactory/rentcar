@extends('layouts.app')
@section('page-title')
    {{__('Google ReCaptcha Settings')}}
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
                    <form action="{{ route('setting.google.recaptcha') }}" method="POST">
                    @csrf
                    <div class="row mt-2">
                        <div class="col-auto">
                            <label for="google_recaptcha" class="form-label">{{ __('Google ReCaptch Enable') }}</label>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check custom-chek">
                                    <input class="form-check-input" type="checkbox" name="google_recaptcha" id="google_recaptch" {{ $settings['google_recaptcha'] == 'on' ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="recaptcha_key" class="form-label">{{ __('Recaptcha Key') }}</label>
                            <input type="text" name="recaptcha_key" id="recaptcha_key" class="form-control" placeholder="{{ __('Enter recaptcha key') }}" value="{{ old('recaptcha_key', $settings['recaptcha_key']) }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="recaptcha_secret" class="form-label">{{ __('Recaptcha Secret') }}</label>
                            <input type="text" name="recaptcha_secret" id="recaptcha_secret" class="form-control" placeholder="{{ __('Enter recaptcha secret') }}" value="{{ old('recaptcha_secret', $settings['recaptcha_secret']) }}">
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
