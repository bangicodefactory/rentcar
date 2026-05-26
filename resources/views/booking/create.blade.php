@extends('layouts.app')
@section('page-title')
    {{ __('Inspection') }}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{ route('dashboard') }}">
                <h1>{{ __('Dashboard') }}</h1>
            </a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('booking.index') }}">
                {{ __('Booking') }}
            </a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{ __('Create') }}
            </a>
        </li>
    </ul>
@endsection
@section('card-action-btn')
@endsection
@section('content')
    <form action="{{ url('booking') }}" method="POST" id="myForm">
    @csrf
    <div class="row">
        <div class="col-md-12 col-lg-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <input type="hidden" name="details" id="details">
                        <div class="form-group col-md-4 col-lg-4">
                            <label for="start_date_time" class="form-label">{{ __('Start Date Time') }}</label>
                            <input type="text" name="start_date_time" id="start_date_time" class="form-control start_date_time" placeholder="{{ __('Select Start Date & Time') }}" value="{{ old('start_date_time') }}" required>
                        </div>
                        <div class="form-group col-md-4 col-lg-4">
                            <label for="end_date_time" class="form-label">{{ __('End Date Time') }}</label>
                            <input type="text" name="end_date_time" id="end_date_time" class="form-control end_date_time" placeholder="{{ __('Select End Date & Time') }}" value="{{ old('end_date_time') }}" required>
                        </div>
                        <div class="form-group col-md-4 col-lg-4">
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
                        <div class="form-group col-md-4 col-lg-4">
                            <label for="driver" class="form-label">{{ __('Driver') }}</label>
                            <select name="driver" id="driver" class="form-control basic-select">
                                @foreach($driversDropdown as $driverId => $driverName)
                                    <option value="{{ $driverId }}">{{ $driverName }}</option>
                                @endforeach
                            </select>
                            <span class="float-end"> <a class=" customModal" href="#" data-size="lg"
                                    data-url="{{ route('driver.new.create') }}"
                                    data-title="{{ __('Create Driver') }}">{{ __('Create New Driver') }}</a></span>
                        </div>

                        <div class="form-group col-md-4 col-lg-4">
                            <label for="pickup_address" class="form-label">{{ __('Pickup Address') }}</label>
                            <select name="pickup_address" id="pickup_address" class="form-control basic-select" required>
                                <option value="">{{ __('Select Pickup Address') }}</option>
                                @foreach ($places as $place)
                                    <option value="{{ $place->id }}">{{ $place->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4 col-lg-4">
                            <label for="drop_off_address" class="form-label">{{ __('Drop Off Address') }}</label>
                            <select name="drop_off_address" id="drop_off_address" class="form-control basic-select"
                                required>
                                <option value="">{{ __('Select Drop Off Address') }}</option>
                                @foreach ($places as $place)
                                    <option value="{{ $place->id }}">{{ $place->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4 col-lg-4">
                            <label for="addon" class="form-label">{{ __('Addon') }}</label>
                            <select name="addon[]" id="addon" class="form-control hidesearch addon" multiple>
                                @foreach($addon as $val => $label)
                                    <option value="{{ $val }}" {{ in_array($val, old('addon', [])) ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- add discount input  --}}

                        <div class="form-group col-md-4 col-lg-4">
                            <label for="discount" class="form-label">{{ __('Discount') }}</label>
                            <input type="number" name="discount" id="discount" class="form-control" step="any" min="0" placeholder="{{ __('Enter discount') }}" value="{{ old('discount') }}">
                        </div>

                        <div class="form-group col-md-4 col-lg-4">
                            <label for="status" class="form-label">{{ __('Status') }}</label>
                            <select name="status" id="status" class="form-control hidesearch " required>
                                @foreach($status as $val => $label)
                                    <option value="{{ $val }}" {{ old('status') == $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <input type="hidden" name="amount" id="amount">
                        <div class="form-group col-md-4 col-lg-4">
                            <label for="notes" class="form-label">{{ __('Notes') }}</label>
                            <textarea name="notes" id="notes" class="form-control" placeholder="{{ __('Enter notes') }}" rows="2">{{ old('notes') }}</textarea>
                        </div>
{{-- Final Price  --}}
                        <div class="form-group col-md-4 col-lg-4">
                            <label for="daily_price" class="form-label">{{ __('Price a day') }}</label>
                            <input type="number" name="daily_price" id="daily_price" class="form-control" step="any" min="0" value="{{ old('daily_price') }}">
                        </div>

                        <div class="col-md-6 col-lg-6 detail_div d-none">
                            <table class="display dataTable cell-border">
                                <tbody class="text-center" id="detail_table">
                                    <tr>
                                        <td> {{ __('Duration') }}</td>
                                        <td class="duration"></td>
                                    </tr>
                                </tbody>
                                <tbody class="text-center" id="addonData"></tbody>
                                <tbody class="text-center" id="placeData"></tbody>
                                <tbody class="text-center" id="pickupPlace"></tbody>
                                <tbody class="text-center" id="dropPlace"></tbody>
                                
                                <tbody class="text-center">
                                    <tr>
                                        <td><b class="h6">{{ __('Discount') }}</b></td>
                                        <td><b class="h6"> <span id="discountPlace"></span></b></td>
                                    </tr>
                                </tbody>
                                <tbody class="text-center">
                                    <tr>
                                        <td><b class="h6">{{ __('Total Amount') }}</b></td>
                                        <td><b class="h6"> <span id="totalAmount"></span></b></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row text-end">
        <div class="col-md-12 col-lg-12">
            <button type="submit" class="btn btn-primary ml-10">{{ __('Create') }}</button>
        </div>
    </div>
    </form>
@endsection
@push('script-page')
    <script>
        $(document).ready(function() {
            // editing datatime to get acces a select more date 
            // var today = new Date();


            var today = new Date();
            today.setMonth(today.getMonth() - 1);
            
            if ($('.start_date_time').length > 0) {
                $('.start_date_time').datetimepicker({
                    step: 15,
                    minDate: today,
                    onClose: function(current_time, $input) {
                        var start_date_time = $input.val();
                        var end_date_time_picker = $('.end_date_time');

                        if (start_date_time) {
                            end_date_time_picker.datetimepicker('setOptions', {
                                minDate: new Date(start_date_time)
                            });
                            end_date_time_picker.prop('disabled', false);
                        } else {
                            end_date_time_picker.prop('disabled', true);
                        }
                    }
                });
            }

            if ($('.end_date_time').length > 0) {
                $('.end_date_time').datetimepicker({
                    step: 15,
                    minDate: today
                }).prop('disabled', true);
            }
        });
    </script>

    <script>
        $(document).on('click', '.create_btn', function(e) {
            var formData = $("form").serialize();
            $.ajax({
                url: "{{ route('driver.store') }}",
                type: "POST",
                data: formData,
                success: function(result) {
                    var response = JSON.parse(result);
                    if (response.status == false) {
                        toastrs('error', response.data, 'error');
                        $("#customModal").modal('hide');
                        return false;
                    }

                    if (response.status && response.data) {
                        var selectElement = $("#driver");
                        selectElement.empty();
                        // Add default option first
                        selectElement.append('<option value="">{{ __("Select Driver") }}</option>');
                        // Sort keys in ascending order to maintain consistent driver order
                        var keys = Object.keys(response.data).sort(function(a, b) {
                            return a - b;
                        });
                        keys.forEach(function(key) {
                            var option = $("<option></option>").attr("value", key).text(response
                                .data[key]);
                            selectElement.append(option);
                        });
                    }
                    $("#customModal").modal('hide');
                },
                error: function(result) {
                    toastrs('error', result, 'error')
                }
            });
        });
    </script>
    <script>
        $(document).on('change', '#start_date_time,#end_date_time', function(e) {
            var start_date_time = $("#start_date_time").val();
            var end_date_time = $("#end_date_time").val();
            start_datetime = start_date_time;
            if (start_date_time != '' && end_date_time != '') {
                $.ajax({
                    url: "{{ route('available.vehicle') }}",
                    type: "GET",
                    data: {
                        start_date_time: start_date_time,
                        end_date_time: end_date_time,
                    },
                    success: function(result) {
                        var response = JSON.parse(result);

                        var selectElement = $("#vehicle");
                        selectElement.empty();
                        // Add default option first
                        selectElement.append('<option value="">{{ __("Select Vehicle") }}</option>');
                        // Sort keys in ascending order to maintain consistent vehicle order
                        var keys = Object.keys(response).sort(function(a, b) {
                            return a - b;
                        });
                        keys.forEach(function(key) {
                            var option = $("<option></option>").attr("value", key).text(
                                response[key]);
                            selectElement.append(option);
                        });
                        $('#vehicle').trigger('change');
                    },
                    error: function(result) {
                        toastrs('error', result, 'error')
                    }
                });
            }
        });


        $(document).on('change', '#vehicle', function(e) {
            var vehicle_id = $("#vehicle").val();
            var start_date_time = $("#start_date_time").val();
            var end_date_time = $("#end_date_time").val();
            var addons = $(".addon").val();
            var pickup_place = $("#pickup_address").val();
            var drop_off_place = $("#drop_off_address").val();
            var daily_price = $("#daily_price").val();
            if (vehicle_id != '' && start_date_time != '' && end_date_time != '') {
                $.ajax({
                    url: "{{ route('vehicle.rate.calculation') }}",
                    type: "GET",
                    data: {
                        vahicle_id: vehicle_id,
                        start_date_time: start_date_time,
                        end_date_time: end_date_time,
                        addons: addons,
                        pickup_place: pickup_place,
                        drop_off_place: drop_off_place,
                        daily_price: daily_price,
                    },
                    success: function(result) {
                        $('.detail_div').removeClass('d-none');
                        var response = JSON.parse(result);
                        var totalRate = parseFloat(response['totalRate']) || 0;
                        var addonAmount = parseFloat(response['addonAmount']) || 0;
                        var placeAmount = parseFloat(response['placeAmount']) || 0;
                        var daily_price_data = parseFloat(response['daily_price']) || 0;
                        var sum = totalRate + addonAmount + placeAmount;

                        
                        var discountAmount = parseFloat($('#discount').val()) || 0;
                        var finalSum = sum - discountAmount;

                        $('#daily_price').val(daily_price_data);
                        $('#amount').val(finalSum);
                        $('#details').val(result);

                        $('.duration').html(response['duration']);
                        $('#addonData').html(response['specificAddonCalculation']);
                        $('#totalAmount').html(finalSum);

                        $('#discountPlace').html(discountAmount + ' Dh');
                    },
                    error: function(result) {
                        toastrs('error', result, 'error')
                    }
                });
            }
        });
        $(document).on('change', '#pickup_address,#drop_off_address', function(e) {
            var pickup_place = $("#pickup_address").val();
            var drop_off_place = $("#drop_off_address").val();
            var vehicle_id = $("#vehicle").val();
            var start_date_time = $("#start_date_time").val();
            var end_date_time = $("#end_date_time").val();
            var addons = $(".addon").val();

            var daily_price = $("#daily_price").val();
            var daychange = 1;

            if (pickup_place != '' || drop_off_place != '') {
                $.ajax({
                    url: "{{ route('place.rate.calculation') }}",
                    type: "GET",
                    data: {
                        vahicle_id: vehicle_id,
                        start_date_time: start_date_time,
                        end_date_time: end_date_time,
                        addons: addons,
                        pickup_place: pickup_place,
                        drop_off_place: drop_off_place,
                        daily_price: daily_price,
                        daychange: daychange,
                    },
                    success: function(result) {
                        var response = JSON.parse(result);
                        var totalRate = parseFloat(response['totalRate']) || 0;
                        var addonAmount = parseFloat(response['addonAmount']) || 0;
                        var placeAmount = parseFloat(response['placeAmount']) || 0;
                        var sum = totalRate + addonAmount + placeAmount;

                        var discountAmount = parseFloat($('#discount').val()) || 0;
                        var finalSum = sum - discountAmount;


                        $('#amount').val(finalSum);
                        $('#details').val(result);
                        $('#pickupPlace').html(response['pickup_place']);
                        $('#dropPlace').html(response['drop_place']);
                        $('#totalAmount').html(finalSum);

                        $('#discountPlace').html(discountAmount + ' Dh');
                        console.log(response);
                    console.log(response['duration']);
                    console.log('Daily Price new :' + daily_price);
                    console.log('placeAmount new: ' + placeAmount);
                    },
                    error: function(result) {
                        toastrs('error', result, 'error')
                    }
                });
            }
        });
    </script>
    <script>
        $(document).on('change', '.addon', function(e) {
            var addons = $(this).val();
            var vahicle_id = $("#vehicle").val();
            var start_date_time = $("#start_date_time").val();
            var end_date_time = $("#end_date_time").val();

            var pickup_place = $("#pickup_address").val();
            var drop_off_place = $("#drop_off_address").val();

            var daily_price = $("#daily_price").val();
            var daychange = 1;

            $.ajax({
                url: "{{ route('addon.rate.calculation') }}",
                type: "GET",
                data: {
                    addons: addons,
                    vahicle_id: vahicle_id,
                    start_date_time: start_date_time,
                    end_date_time: end_date_time,
                    pickup_place: pickup_place,
                    drop_off_place: drop_off_place,
                    daily_price: daily_price,
                    daychange: daychange,
                },
                success: function(result) {
                    var response = JSON.parse(result);
                    var totalRate = parseFloat(response['totalRate']) || 0;
                    var addonAmount = parseFloat(response['addonAmount']) || 0;
                    var placeAmount = parseFloat(response['placeAmount']) || 0;
                    var sum = totalRate + addonAmount + placeAmount;

                    var discountAmount = parseFloat($('#discount').val()) || 0;
                    var finalSum = sum - discountAmount;

                    $('#amount').val(finalSum);
                    $('#details').val(result);
                    $('#addonData').html(response['specificAddonCalculation']);
                    $('#totalAmount').html(finalSum);

                    $('#discountPlace').html(discountAmount + ' Dh');
                },
                error: function(result) {
                    toastrs('error', result, 'error')
                }
            });
        });
</script>
<script>
        $(document).on('change', '#discount', function(e) {          
            var addons = $(".addon").val();
            var vahicle_id = $("#vehicle").val();
            var start_date_time = $("#start_date_time").val();
            var end_date_time = $("#end_date_time").val();

            var pickup_place = $("#pickup_address").val();
            var drop_off_place = $("#drop_off_address").val();

            var daily_price = $("#daily_price").val();
            var daychange = 1;

            $.ajax({
                url: "{{ route('addon.rate.calculation') }}",
                type: "GET",
                data: {
                    addons: addons,
                    vahicle_id: vahicle_id,
                    start_date_time: start_date_time,
                    end_date_time: end_date_time,
                    pickup_place: pickup_place,
                    drop_off_place: drop_off_place,
                    daily_price: daily_price,
                    daychange: daychange,
                },
                success: function(result) {
                    var response = JSON.parse(result);
                    var totalRate = parseFloat(response['totalRate']) || 0;
                    var addonAmount = parseFloat(response['addonAmount']) || 0;
                    var placeAmount = parseFloat(response['placeAmount']) || 0;
                    var sum = totalRate + addonAmount + placeAmount;

                    var discountAmount = parseFloat($('#discount').val()) || 0;
                    var finalSum = sum - discountAmount;

                    $('#amount').val(finalSum);
                    $('#details').val(result);
                    $('#addonData').html(response['specificAddonCalculation']);
                    $('#totalAmount').html(finalSum);

                    $('#discountPlace').html(discountAmount + ' Dh');
                },
                error: function(result) {
                    toastrs('error', result, 'error')
                }
            });
    });
    // price day 
        $(document).on('change', '#daily_price', function(e) {          
            var addons = $(".addon").val();
            var vahicle_id = $("#vehicle").val();
            var start_date_time = $("#start_date_time").val();
            var end_date_time = $("#end_date_time").val();

            var pickup_place = $("#pickup_address").val();
            var drop_off_place = $("#drop_off_address").val();

            console.log('pickup_place: ' + pickup_place);
            console.log('drop_off_place: ' + drop_off_place);

            var daily_price = $("#daily_price").val();
            var daychange = 1;
            $.ajax({
                url: "{{ route('vehicle.rate.calculation') }}",
                type: "GET",
                data: {
                    addons: addons,
                    vahicle_id: vahicle_id,
                    start_date_time: start_date_time,
                    end_date_time: end_date_time,
                    pickup_place: pickup_place,
                    drop_off_place: drop_off_place,
                    daily_price: daily_price,
                    daychange: daychange,
                },
                success: function(result) {
                    var response = JSON.parse(result);
                    var totalRate = parseFloat(response['totalRate']) || 0;
                    var addonAmount = parseFloat(response['addonAmount']) || 0;
                    var placeAmount = parseFloat(response['placeAmount']) || 0;
                    var sum = totalRate + addonAmount + placeAmount;

                    var discountAmount = parseFloat($('#discount').val()) || 0;
                    var finalSum = sum - discountAmount;

                    
                    $('#amount').val(finalSum);
                    $('#details').val(result);
                    $('#addonData').html(response['specificAddonCalculation']);
                    $('#totalAmount').html(finalSum);

                    $('#discountPlace').html(discountAmount + ' Dh');

                    $('.duration').html(response['duration']);

                    console.log(response);
                    console.log(response['duration']);
                    console.log('Daily Price new :' + daily_price);
                    console.log('Addon Amout new: ' + addonAmount);
                    console.log('placeAmount:'  + placeAmount);
                },
                error: function(result) {
                    toastrs('error', result, 'error')
                }
            });
    });
       
    </script>
@endpush