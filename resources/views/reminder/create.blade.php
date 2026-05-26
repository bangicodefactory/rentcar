@extends('layouts.app')
@section('page-title')
    {{ __('Reminder') }}
@endsection


@section('content')

<form action="{{ url('reminder') }}" method="POST" enctype="multipart/form-data">
@csrf
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-6">
            <label for="name" class="form-label">{{ __('Title') }}</label>
            <input type="text" name="name" id="name" class="form-control" placeholder="{{ __('Enter reminder titel') }}" value="{{ old('name') }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="type" class="form-label">{{ __('Reminder Type') }}</label>
            <select name="type" id="type" class="form-control hidesearch ">
                @foreach($types as $val => $label)
                    <option value="{{ $val }}" {{ old('type') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6">
            <label for="vehicle" class="form-label">{{ __('Vehicle') }}</label>
            <select name="vehicle" id="vehicle" class="form-control basic-select" required>
                <option value="">{{ __('Select Vehicle') }}</option>
                @foreach ($vehicles as $vehicle)
                    <option value="{{ $vehicle->id }}">
                        {{ $vehicle->name . ' - ' . $vehicle->license_plate }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group col-md-6">
            <label for="reminder_date" class="form-label">{{ __('Reminder Date') }}</label>
            <input type="date" name="reminder_date" id="reminder_date" class="form-control" value="{{ old('reminder_date') }}" required>
        </div>
        <div class="form-group col-md-12">
            <label for="note" class="form-label">{{ __('Notes') }}</label>
            <textarea name="note" id="note" class="form-control" placeholder="{{ __('Reminder Description') }}" rows="2">{{ old('note') }}</textarea>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{ __('Close') }}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Create')}}</button>
</div>
</form>

@endsection
