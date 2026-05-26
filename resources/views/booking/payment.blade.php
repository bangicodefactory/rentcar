<form action="{{ route('booking.payment.store', $booking->id) }}" method="POST" id="payment-create-form">
@csrf
<div class="modal-body">
    <div class="row">
        <div id="payment-error" class="alert alert-danger d-none" role="alert" tabindex="-1" style="display:none;"></div>
        <div class="form-group">
            <label for="date" class="form-label">{{ __('Date') }}</label>
            <input type="date" name="date" id="date" class="form-control" value="{{ old('date', date('Y-m-d')) }}" required>
        </div>
        <div class="form-group">
            <label for="amount" class="form-label">{{ __('Amount') }}</label>
            <input type="number" name="amount" id="amount" class="form-control" placeholder="{{ __('Enter payment amount') }}" value="{{ old('amount', $booking->getTotalDueAmount()) }}" required>
        </div>
        <div class="form-group">
            <label for="quantity" class="form-label">{{ __('Quantity (Days)') }}</label>
            <input type="number" name="quantity" id="quantity" class="form-control" placeholder="{{ __('Enter quantity') }}" value="{{ old('quantity', $defaultQuantity) }}" required min="1" step="1">
        </div>
        <div class="form-group">
            <label for="payment_method" class="form-label">{{ __('Method') }}</label>
            <select name="payment_method" id="payment_method" class="form-control hidesearch ">
                @foreach($paymentMethod as $val => $label)
                    <option value="{{ $val }}" {{ old('payment_method') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="notes" class="form-label">{{ __('Notes') }}</label>
            <textarea name="notes" id="notes" class="form-control" placeholder="{{ __('Enter notes') }}" rows="1">{{ old('notes') }}</textarea>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{__('Close')}}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Create')}}</button>
</div>
</form>

@push('script-page')
<script>
(function(){
    var form = document.getElementById('payment-create-form');
    if(!form) return;
    var amountInput = form.querySelector('input[name="amount"]');
    var methodSelect = form.querySelector('select[name="payment_method"]');
    var errorBox = document.getElementById('payment-error');
    function hideError(){
        if(!errorBox) return;
        errorBox.classList.add('d-none');
        errorBox.style.display='none';
        errorBox.textContent='';
    }
    function showError(msg){
        if(!errorBox) return;
        errorBox.textContent = msg;
        errorBox.classList.remove('d-none');
        errorBox.style.display='block';
        errorBox.focus();
    }
    ['input','change','keyup'].forEach(function(ev){
        amountInput.addEventListener(ev, hideError);
        methodSelect.addEventListener(ev, hideError);
    });
    form.addEventListener('submit', function(e){
        var amount = parseFloat(amountInput.value || '0');
        var selectedOption = methodSelect.options[methodSelect.selectedIndex];
        function normalize(str){
            return (str||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
        }
    var methodText = normalize(selectedOption ? selectedOption.text : '');
    var methodValue = normalize(methodSelect.value||'');
    // methodText/methodValue already normalized to lowercase + accent removed
    var isCash = methodText === 'espece' || methodValue === 'espece';
        if(amount > 5000 && isCash){
            e.preventDefault();
            showError(@json(__('Cash payments over 5000 are not allowed. Please choose another method.')));
            return;
        }
        // AJAX submit to keep modal open on error
        e.preventDefault();
        hideError();
        var formData = new FormData(form);
        fetch(form.getAttribute('action'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
            },
            body: formData
        }).then(function(r){
            if(!r.ok){
                return r.json().then(function(j){ throw j; });
            }
            return r.json();
        }).then(function(data){
            if(data.status === 'success'){
                // Optionally refresh part of the page or reload to update payment list
                window.location.reload();
            } else if(data.message){
                showError(data.message);
            }
        }).catch(function(err){
            if(err && err.message){
                showError(err.message);
            } else if(err && err.status==='error' && err.message){
                showError(err.message);
            } else {
                showError(@json(__('An unexpected error occurred.')));
            }
        });
    });
})();
</script>
@endpush
