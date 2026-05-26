@extends('layouts.app')
@section('page-title')
    {{__('Inspection')}}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{route('dashboard')}}"><h1>{{__('Dashboard')}}</h1></a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{route('inspection.index')}}">
                {{__('Inspection')}}
            </a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{__('Edit')}}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')

@endsection
@section('content')
    <form action="{{ route('inspection.update', $inspection->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="row">
        <div class="col-md-6 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4>{{__('Inspection Details')}}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6 col-lg-6">
                            <label for="vehicle" class="form-label">{{ __('Vehicle') }}</label>
                            <select name="vehicle" id="vehicle" class="form-control hidesearch " required>
                                @foreach($vehicles as $val => $label)
                                    <option value="{{ $val }}" {{ old('vehicle', $inspection->vehicle) == $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6 col-lg-6">
                            <label for="inspector" class="form-label">{{ __('Inspection By') }}</label>
                            <input type="text" name="inspector" id="inspector" class="form-control" value="{{ old('inspector', $inspection->inspector) }}" required>
                        </div>
                        <div class="form-group col-md-6 col-lg-6">
                            <label for="inspection_date" class="form-label">{{ __('Inspection Date') }}</label>
                            <input type="date" name="inspection_date" id="inspection_date" class="form-control" value="{{ old('inspection_date', $inspection->inspection_date) }}" required>
                        </div>
                        <div class="form-group col-md-6 col-lg-6">
                            <label for="status" class="form-label">{{ __('Inspection Status') }}</label>
                            <select name="status" id="status" class="form-control hidesearch " required>
                                @foreach($status as $val => $label)
                                    <option value="{{ $val }}" {{ old('status', $inspection->status) == $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6 col-lg-6">
                            <label for="repair_status" class="form-label">{{ __('Repair Status') }}</label>
                            <select name="repair_status" id="repair_status" class="form-control hidesearch " required>
                                @foreach($repairStatus as $val => $label)
                                    <option value="{{ $val }}" {{ old('repair_status', $inspection->repair_status) == $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6 col-lg-6">
                            <label for="notes" class="form-label">{{ __('Notes') }}</label>
                            <textarea name="notes" id="notes" class="form-control" placeholder="{{ __('Enter notes') }}" rows="2" required>{{ old('notes', $inspection->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-6">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6 col-lg-6">
                                    <label for="amount" class="form-label">{{ __('Amount') }}</label>
                                    <input type="number" name="amount" id="amount" class="form-control" placeholder="{{ __('Enter amount') }}" value="{{ old('amount', $inspection->amount) }}" required>
                                </div>
                                <div class="form-group col-md-6 col-lg-6">
                                    <label for="receipt" class="form-label">{{ __('Receipt') }}</label>
                                    <input type="file" name="receipt" id="receipt" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>{{__('Incoming Details')}}</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6 col-lg-6">
                                    <label for="incoming_date" class="form-label">{{ __('Date') }}</label>
                                    <input type="date" name="incoming_date" id="incoming_date" class="form-control" value="{{ old('incoming_date', $inspection->incoming_date) }}">
                                </div>

                                <div class="form-group col-md-6 col-lg-6">
                                    <label for="meter_reading_incoming" class="form-label">{{ __('Meter Reading (km)') }}</label>
                                    <input type="number" name="meter_reading_incoming" id="meter_reading_incoming" class="form-control" placeholder="{{ __('Enter meter reading incoming (km)') }}" value="{{ old('meter_reading_incoming', $inspection->meter_reading_incoming) }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h4>{{__('Inspections Checklist')}}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($types as $type)
                            <div class="col-md-6 col-lg-6">
                                <h6 class="form-label">{{$type->type}}</h6>
                                <div class="col-md-12 col-lg-12">
                                    <div class="row">
                                        <div class="form-group col-auto">
                                            <label class="switch with-icon switch-primary">
                                                <input type="checkbox" name="types[{{$type->id}}][type]" {{isset($details[$type->id]['type']) && !empty($details[$type->id]['type'])?'checked':''}}><span
                                                    class="switch-btn"></span>
                                            </label>
                                        </div>
                                        <div class="form-group col">
                                            <input class="form-control" type="text" placeholder="{{__('Enter notes')}}" name="types[{{$type->id}}][note]" value="{{isset($details[$type->id]['note']) && !empty($details[$type->id]['note'])?$details[$type->id]['note']:''}}" autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row text-end">
        <div class="col-md-12 col-lg-12">
            <button type="submit" class="btn btn-primary ml-10">{{__('Update')}}</button>
        </div>
    </div>
    </form>
@endsection
