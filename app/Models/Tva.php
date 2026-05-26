<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Booking;

class Tva extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        // Existing columns
        'month',
        'year',
        'total_amount',
        'tva_amount',
        'status',
        'generated_date',
        'created_at',
        'updated_at',

        // New invoice columns
        'facture_number',
        'facture_date',
        'reference',
        'client_name',
        'client_address',
        'company_name',
        'company_address',
        'designation',
        'quantity',
        'unit_price_ht',
        'total_ht',
        'tva',
        'montant_ttc',
        'ice_number',
        'rc_number',
        'tp_number',
        'nif_number',
    'booking_id',
    'idpaiment',
    ];

    protected $casts = [
        'facture_date' => 'date',
        'generated_date' => 'date',
    'quantity' => 'float',
    'unit_price_ht' => 'float',
    'total_ht' => 'float',
    'tva' => 'float',
    'montant_ttc' => 'float',
    'total_amount' => 'float',
    'tva_amount' => 'float',
    ];

    // Accessor for formatted facture date
    public function getFormattedFactureDateAttribute()
    {
        return $this->facture_date ? $this->facture_date->format('d/m/Y') : null;
    }

    // Accessor for formatted quantities and amounts
    public function getFormattedQuantityAttribute()
    {
        return $this->quantity ? number_format($this->quantity, 2, ',', ' ') : null;
    }

    public function getFormattedUnitPriceHtAttribute()
    {
        return $this->unit_price_ht ? number_format($this->unit_price_ht, 2, ',', ' ') : null;
    }

    public function getFormattedTotalHtAttribute()
    {
        return $this->total_ht ? number_format($this->total_ht, 2, ',', ' ') : null;
    }

    public function getFormattedTvaAttribute()
    {
        return $this->tva ? number_format($this->tva, 2, ',', ' ') : null;
    }

    public function getFormattedMontantTtcAttribute()
    {
        return $this->montant_ttc ? number_format($this->montant_ttc, 2, ',', ' ') : null;
    }

    // Scope for filtering by facture number
    public function scopeByFactureNumber($query, $factureNumber)
    {
        return $query->where('facture_number', $factureNumber);
    }

    // Scope for filtering by client
    public function scopeByClient($query, $clientName)
    {
        return $query->where('client_name', 'like', '%' . $clientName . '%');
    }

    // Scope for filtering by date range
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('facture_date', [$startDate, $endDate]);
    }

    // Scope for filtering by year of facture_date
    public function scopeForYear($query, int $year)
    {
        return $query->whereYear('facture_date', $year);
    }

    // Calculate TVA amount based on total HT
    public function calculateTvaAmount()
    {
        if ($this->total_ht && $this->tva) {
            return ($this->total_ht * $this->tva) / 100;
        }
        return 0;
    }

    // Calculate TTC amount
    public function calculateMontantTtc()
    {
        if ($this->total_ht && $this->tva) {
            return $this->total_ht + $this->calculateTvaAmount();
        }
        return $this->total_ht ?? 0;
    }
    // Relations
    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
