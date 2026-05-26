@extends('layouts.app')
@section('page-title')
    {{ __('Reminder') }}
@endsection


@section('content')
<form action="{{ route('reminder.update', $reminder->id) }}" method="POST" enctype="multipart/form-data">
@csrf
@method('PUT')
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-6">
            <label for="name" class="form-label">{{ __('Title') }}</label>
            <input type="text" name="name" id="name" class="form-control" placeholder="{{ __('Enter reminder title') }}" value="{{ old('name', $reminder->name) }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="type" class="form-label">{{ __('Reminder Type') }}</label>
            <select name="type" id="type" class="form-control hidesearch ">
                @foreach($type as $val => $label)
                    <option value="{{ $val }}" {{ old('type', $reminder->type) == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6">
            <label for="vehicle_display" class="form-label">{{ __('Vehicle') }}</label>
            <input type="text" name="vehicle_display" id="vehicle_display" class="form-control" value="{{ $vehicleName }}" readonly>
            <input type="hidden" name="id_vehicle" value="{{ $reminder->id_vehicle }}">
        </div>

        <div class="form-group col-md-6">
            <label for="reminder_date" class="form-label">{{ __('Reminder Date') }}</label>
            <input type="date" name="reminder_date" id="reminder_date" class="form-control" value="{{ old('reminder_date', $reminder->reminder_date) }}" required>
        </div>
        <div class="form-group col-md-12">
            <label for="note" class="form-label">{{ __('Notes') }}</label>
            <textarea name="note" id="note" class="form-control" placeholder="{{ __('Reminder Description') }}" rows="2">{{ old('note', $reminder->note) }}</textarea>
        </div>
         <input name="status" id="status" hidden value="{{ $reminder->status }}">

    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" onclick="window.history.back()">{{__('Close')}}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Update')}}</button>
</div>
</form>


@endsection
