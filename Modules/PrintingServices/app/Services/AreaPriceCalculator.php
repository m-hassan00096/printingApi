<?php

namespace Modules\PrintingServices\Services;

use Illuminate\Validation\ValidationException;
use Modules\PrintingServices\Enums\PricingMethod;

/** Precise financial calculations: inputs are decimal strings, not PHP floats. */
final class AreaPriceCalculator
{
    public function calculate(PricingMethod $method, string $rate, int $quantity, ?string $width, ?string $height): array
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => __('printing_services.messages.quantity_positive')]);
        }
        if ($method === PricingMethod::QUANTITY) {
            $total = bcmul($rate, (string) $quantity, 12);
            return [
                'area' => null,
                'piecePrice' => $this->roundMoney($rate),
                'total' => $this->roundMoney($total),
            ];
        }

        foreach (['widthCm' => $width, 'heightCm' => $height] as $field => $dimension) {
            if ($dimension === null || ! preg_match('/\A(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,4})?\z/D', $dimension)
                || bccomp($dimension, '0', 4) <= 0) {
                throw ValidationException::withMessages([$field => __('printing_services.messages.dimension_positive')]);
            }
        }

        $squareCm = bcmul($width, $height, 8);
        $area = $method === PricingMethod::SQUARE_METER
            ? bcdiv($squareCm, '10000', 12)
            : $squareCm;
        $piecePrice = bcmul($area, $rate, 12);
        $total = bcmul($piecePrice, (string) $quantity, 12);

        return [
            'area' => rtrim(rtrim($area, '0'), '.') ?: '0',
            'piecePrice' => rtrim(rtrim($piecePrice, '0'), '.') ?: '0',
            'total' => $this->roundMoney($total),
        ];
    }

    private function roundMoney(string $value): string
    {
        // Values here are nonnegative. Round half up only once, at line total.
        return bcadd($value, '0.005', 2);
    }
}
