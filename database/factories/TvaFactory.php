<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tva>
 */
class TvaFactory extends Factory
{
    public function definition(): array
    {
        $totalHt     = round($this->faker->randomFloat(2, 100, 2000), 2);
        $tvaRate     = 0.20;
        $tvaAmount   = round($totalHt * $tvaRate, 2);
        $montantTtc  = round($totalHt + $tvaAmount, 2);

        return [
            'month'          => $this->faker->numberBetween(1, 12),
            'year'           => now()->year,
            'total_amount'   => (int) $montantTtc,
            'tva_amount'     => (int) $tvaAmount,
            'status'         => 'generated',
            'generated_date' => now(),
            // Invoice fields (nullable — populated when invoice is created)
            'facture_number' => null,
            'facture_date'   => null,
            'reference'      => null,
            'client_name'    => null,
            'client_address' => null,
            'company_name'   => null,
            'company_address'=> null,
            'designation'    => null,
            'quantity'       => null,
            'unit_price_ht'  => null,
            'total_ht'       => null,
            'tva'            => null,
            'montant_ttc'    => null,
            'booking_id'     => null,
            'idpaiment'      => null,
        ];
    }

    public function withInvoice(): static
    {
        return $this->state(function () {
            $totalHt    = round($this->faker->randomFloat(2, 100, 2000), 2);
            $tvaAmount  = round($totalHt * 0.20, 2);
            $montantTtc = round($totalHt + $tvaAmount, 2);

            return [
                'facture_number'  => $this->faker->unique()->numerify('FACT-####'),
                'facture_date'    => now()->format('Y-m-d'),
                'reference'       => $this->faker->bothify('REF-####'),
                'client_name'     => $this->faker->name(),
                'client_address'  => $this->faker->address(),
                'company_name'    => $this->faker->company(),
                'company_address' => $this->faker->address(),
                'designation'     => 'Location de véhicule',
                'quantity'        => 3,
                'unit_price_ht'   => round($totalHt / 3, 2),
                'total_ht'        => $totalHt,
                'tva'             => 20.0,
                'montant_ttc'     => $montantTtc,
                'total_amount'    => (int) $montantTtc,
                'tva_amount'      => (int) $tvaAmount,
            ];
        });
    }
}
