<form action="{{ url('rental-agreement') }}" method="POST">
@csrf
<div class="modal-body">
    <div class="row">
        {{-- Driver section --}}
        <div class="form-group col-md-6 col-lg-6">
            <label for="driver" class="form-label">{{ __('Driver') }}</label>
            <select name="driver" id="driver" class="form-control basic-select" required>
                {{-- <option value="">{{ __('Select Driver') }}</option> --}}
                @foreach ($driversDropdown as $driverId => $driverName)
                    <option value="{{ $driverId }}">{{ $driverName }}</option>
                @endforeach
            </select>
            <span class="float-end"> <a class=" customModal" href="#" data-size="lg"
                                    data-url="{{ route('driver.new.create') }}"
                                    data-title="{{ __('Create Driver') }}">{{ __('Create New Driver') }}</a></span>

            <div id="driver-credit-info" class="mt-2" style="display:none;">
                <div class="alert alert-secondary mb-0 p-2" role="alert">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong>{{ __('Total Unpaid Credit:') }}</strong>
                        <span id="credit-amount" class="font-weight-bold" style="font-size: 1.1em;"></span>
                    </div>
                    <hr class="my-1">
                    <p class="mb-1 text-muted small">{{ __('Recent History:') }}</p>
                    <ul id="credit-history" class="list-unstyled mb-0 small pl-0"></ul>
                </div>
            </div>

        </div>

        <div class="form-group col-md-6 col-lg-6">
            <label for="driver2" class="form-label">{{ __('Driver2') }}</label>
            <select name="driver2" id="driver2" class="form-control basic-select">
                {{-- <option value="">{{ __('Select Driver') }}</option> --}}
                @foreach ($driversDropdown as $driverId => $driverName)
                    <option value="{{ $driverId }}">{{ $driverName }}</option>
                @endforeach
            </select>
        </div>
        {{-- driver section  --}}

        {{-- Vehicle section --}}
        <div class="form-group col-md-6 col-lg-6">
            <label for="vehicle" class="form-label">{{ __('Vehicle') }}</label>
            <select name="vehicle" id="vehicle" class="form-control basic-select" required>
                <option value="">{{__('Select Vehicle')}}</option>
                @foreach($vehicles as $vehicle)
                    <option
                        value="{{$vehicle->id}}">{{$vehicle->name.' - '.$vehicle->license_plate}}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6 col-lg-6">
            <label for="rental_start_date" class="form-label">{{ __('Rental Start Date') }}</label>
            <div class="d-flex">
                <input type="date" name="rental_start_date" id="rental_start_date" class="form-control" value="{{ old('rental_start_date') }}" required>
                <input type="time" name="rental_start_time" id="rental_start_time" class="form-control ms-2" value="{{ old('rental_start_time') }}" required>
            </div>
        </div>

        <div class="form-group col-md-6 col-lg-6">
            <label for="rental_end_date" class="form-label">{{ __('Rental End Date') }}</label>
            <div class="d-flex">
                <input type="date" name="rental_end_date" id="rental_end_date" class="form-control" value="{{ old('rental_end_date') }}" required>
                <input type="time" name="rental_end_time" id="rental_end_time" class="form-control ms-2" value="{{ old('rental_end_time') }}" required>
            </div>
        </div>

        <div class="form-group col-md-6 col-lg-6">
            <label for="rental_duration" class="form-label">{{ __('Rental Duration (Days)') }}</label>
            <input type="number" name="rental_duration" id="rental_duration" class="form-control" placeholder="{{ __('Enter rental duration') }}" value="{{ old('rental_duration') }}" required>
        </div>
        <div class="form-group col-md-6 col-lg-6">
            <label for="status" class="form-label">{{ __('Status') }}</label>
            <select name="status" id="status" class="form-control hidesearch " required>
                @foreach($status as $val => $label)
                    <option value="{{ $val }}" {{ old('status') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-12 col-lg-12">
            <label for="terms_condition" class="form-label">{{ __('Terms & Condition') }}</label>
            <textarea name="terms_condition" id="terms_condition" class="form-control" placeholder="{{ __('Enter terms & condition') }}" rows="7">{{ old('terms_condition', config('default_terms.rental_agreement')) }}</textarea>
        </div>
        <div class="form-group col-md-12 col-lg-12">
            <label for="description" class="form-label">{{ __('Description') }}</label>
            <textarea name="description" id="description" class="form-control" placeholder="{{ __('Enter description') }}" rows="5">{{ old('description') }}</textarea>
        </div>
        <div class="form-group col-md-12 col-lg-12">
            <input type="hidden" name="create_booking" value="0">
            <div class="form-check">
                <input type="checkbox" name="create_booking" id="create_booking" class="form-check-input" value="1" {{ old('create_booking', false) ? 'checked' : '' }}>
                <label for="create_booking" class="form-check-label">{{ __('Create booking from this rental agreement') }}</label>
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{ __('Close') }}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Create')}}</button>
</div>
</form>
<script>
    $(document).on('change', '#driver', function() {
        var driverId = $(this).val();
        if (driverId) {
            var url = '{{ route("credit.driver.details", ["driver_id" => "driver_id_placeholder"]) }}';
            url = url.replace('driver_id_placeholder', driverId);

            $.ajax({
                url: url,
                type: 'GET',
                success: function(response) {
                    $('#driver-credit-info').show();
                    // Assuming currency symbol is not provided by backend, just number
                    $('#credit-amount').text(parseFloat(response.total_unpaid).toFixed(2));

                    var alertBox = $('#driver-credit-info .alert');

                    // Simple logic: if unpaid > 0, danger.
                    // alertBox.removeClass('alert-secondary alert-success').addClass('alert-danger'); // Removed to keep text clearer on grey background
                    // alertBox.removeClass('alert-secondary alert-danger').addClass('alert-success');

                    // Reset to secondary just in case
                    alertBox.removeClass('alert-success alert-danger').addClass('alert-secondary');

                    if(parseFloat(response.total_unpaid) > 0) {
                        $('#credit-amount').removeClass('text-success').addClass('text-danger');
                    } else {
                        $('#credit-amount').removeClass('text-danger').addClass('text-success');
                    }

                    var historyHtml = '';
                    if(response.history && response.history.length > 0) {
                        $.each(response.history, function(index, item) {
                            var color = item.status == 'payé' ? 'text-success' : 'text-danger';
                            historyHtml += '<li class="d-flex justify-content-between ' + color + '"><span>' + item.date + '</span> <span>' + parseFloat(item.amount).toFixed(2) + ' (' + item.status + ')</span></li>';
                        });
                    } else {
                         historyHtml = '<li class="text-muted">{{ __("No history found") }}</li>';
                    }
                    $('#credit-history').html(historyHtml);
                },
                error: function() {
                     $('#driver-credit-info').hide();
                }
            });
        } else {
            $('#driver-credit-info').hide();
        }
    });
</script>


