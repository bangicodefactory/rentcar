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
        {{Form::open(array('route'=>'password.confirm','method'=>'post'))}}
        <div class="form-group mb-0">
            {{Form::label('password',__('Password'),['class'=>'form-label'])}}
            {{Form::password('password',array('class'=>'form-control','placeholder'=>__('Enter your password')))}}
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
        {{Form::close()}}
    </div>
@endsection
