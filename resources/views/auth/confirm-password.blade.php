@extends('layouts.auth')
@section('tab-title')
    {{__('Confirm Password')}}
@endsection
@section('content')
    <div class="codex-authbox">
        <div class="auth-header">
            <h3>{{ __('Confirm Password') }}</h3>
            <p>{{ __('Please confirm your password before continuing.') }}</p>
        </div>
        @if (session('status'))
            <div class="alert alert-primary">
                {{ session('status') }}
            </div>
        @endif
        <form action="{{ route('password.confirm') }}" method="POST">
            @csrf
        <div class="form-group mb-0">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input type="password" name="password" id="password" class="form-control" placeholder="{{ __('Enter your password') }}">
            @error('password')
            <span class="invalid-feedback text-danger" role="alert">
                <strong>{{ $message }}</strong>
            </span>
            @enderror
        </div>
        <div class="form-group mb-0">
            <button class="btn btn-primary" type="submit">{{ __('Confirm') }}</button>
        </div>
        <div class="auth-footer">
            <h6 class="text-center">{{__('Back to')}} <a class="text-primary" href="{{ route('login') }}">{{__('Log In')}}</a></h6>
        </div>
        </form>
    </div>
@endsection
