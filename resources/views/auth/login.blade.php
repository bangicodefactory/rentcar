@extends('layouts.auth')
@php
    $settings=settings();
@endphp
@section('tab-title')
    {{__('Login')}}
@endsection
@push('script-page')
    @if ($settings['google_recaptcha'] == 'on')
        {!! NoCaptcha::renderJs() !!}
    @endif
@endpush
@section('content')
    @php
        $registerPage=getSettingsValByName('register_page');
    @endphp
    <div class="codex-authbox">
        <div class="auth-header">
            <div class="codex-brand">
                <a href="#">
                    <img class="img-fluid light-logo" src="{{asset(Storage::url('upload/logo/')).'/logo.png'}}" alt="">
                    <img class="img-fluid dark-logo" src="{{asset(Storage::url('upload/logo/')).'/logo.png'}}"
                         alt="">
                </a>
            </div>
            {{-- <h3>{{__('Welcome to')}} {{env('APP_NAME')}}</h3> --}}
            <h3>{{__('Welcome to')}} Bangi Car</h3>

        </div>
        <form action="{{ route('login') }}" method="POST" id="loginForm" class="login-form">
        @csrf
        @if (session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif
        <div class="form-group">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input type="text" name="email" id="email" class="form-control" placeholder="{{ __('Enter Your Email') }}" value="{{ old('email') }}">
            @error('email')
            <span class="invalid-email text-danger" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
            @enderror

        </div>
        <div class="form-group">
            <label class="form-label" for="Password">{{__('Password')}}</label>
            <div class="input-group group-input">
                <input class="form-control showhide-password" type="password" name="password" id="Password"
                       placeholder="{{__('Enter Your Password')}}" required="">
                <span class="input-group-text toggle-show fa fa-eye"></span>
            </div>
            @error('password')
            <span class="invalid-password text-danger" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
            @enderror
        </div>
        <div class="form-group mb-0">
            <div class="auth-remember">
                <div class="form-check custom-chek">
                    <input class="form-check-input" id="agree" type="checkbox"
                           value="" {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="agree">{{__('Remember me')}}</label>
                </div>
                @if (Route::has('password.request'))
                    <a class="text-primary f-pwd"
                       href="{{ route('password.request') }}">{{__('Forgot your password?')}}</a>
                @endif
            </div>
        </div>
        @if ($settings['google_recaptcha'] == 'on')
            <div class="form-group">
                <label for="email" class="form-label"></label>
                {!! NoCaptcha::display() !!}
                @error('g-recaptcha-response')
                <span class="small text-danger" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>
            @if ($errors->has('g-recaptcha-response'))
                <span class="help-block">
                    <strong>{{ $errors->first('g-recaptcha-response') }}</strong>
                </span>
            @endif
        @endif
        <div class="form-group">
            <button class="btn btn-primary" type="submit"><i class="fa fa-sign-in"></i> {{__('Login')}}</button>
        </div>
        </form>
        <div class="auth-footer">
            @if($registerPage=='on')
                <h6 class="text-center">{{__("Don't Have An Account?")}} <a class="text-primary"
                                                                            href="{{ route('register') }}">{{__('Create an account')}}</a>
                </h6>
            @endif
        </div>


    </div>
@endsection

