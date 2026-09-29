<?php
declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Domain\Billing\GstCalculator;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

final class OrderCalculationTest extends TestCase
{
    public function testGstCalculationIntrastate(): void
    {
        $calculator = new GstCalculator();
        $lines = [
            [
                'taxable_amount' => '1000.00',
                'gst_percent'    => '12.00',
            ],
            [
                'taxable_amount' => '2000.00',
                'gst_percent'    => '18.00',
            ],
        ];

        $res = $calculator->calculate($lines, GstCalculator::INTRASTATE);

        $this->assertSame('3000.00', $res['taxable_total']);
        $this->assertSame('240.00', $res['cgst_total']);
        $this->assertSame('240.00', $res['sgst_total']);
        $this->assertSame('0.00', $res['igst_total']);
        $this->assertSame('480.00', $res['tax_total']);
        $this->assertSame('3480.00', $res['grand_total']);
    }

    public function testGstCalculationInterstate(): void
    {
        $calculator = new GstCalculator();
        $lines = [
            [
                'taxable_amount' => '1000.00',
                'gst_percent'    => '12.00',
            ],
            [
                'taxable_amount' => '2000.00',
                'gst_percent'    => '18.00',
            ],
        ];

        $res = $calculator->calculate($lines, GstCalculator::INTERSTATE);

        $this->assertSame('3000.00', $res['taxable_total']);
        $this->assertSame('0.00', $res['cgst_total']);
        $this->assertSame('0.00', $res['sgst_total']);
        $this->assertSame('480.00', $res['igst_total']);
        $this->assertSame('480.00', $res['tax_total']);
        $this->assertSame('3480.00', $res['grand_total']);
    }

    public function testCreditExposureMath(): void
    {
        $creditLimitPaise = Money::fromDecimal('50000.00'); // 5000000 paise
        $currentOutstandingPaise = Money::fromDecimal('20000.00'); // 2000000 paise
        $orderAmountPaise = Money::fromDecimal('15000.00'); // 1500000 paise

        $projectedOutstanding = $currentOutstandingPaise + $orderAmountPaise;
        $this->assertSame(3500000, $projectedOutstanding);
        $this->assertLessThanOrEqual($creditLimitPaise, $projectedOutstanding);

        $availableCredit = max(0, $creditLimitPaise - $currentOutstandingPaise);
        $this->assertSame('30000.00', Money::toDecimal($availableCredit));

        $postOrderAvailable = max(0, $creditLimitPaise - $projectedOutstanding);
        $this->assertSame('15000.00', Money::toDecimal($postOrderAvailable));
    }
}
