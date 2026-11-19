<?php
namespace App\Services;

/**
 * The payment methods a shop can take. Only 'cash' touches the counter cash drawer, 'credit' is a sale on account
 * and is not a method of payment.
 */
class PaymentModes
{
    public const MODES = [
        'cash' => 'Cash',
        'cheque' => 'Cheque',
        'raast' => 'Raast / Bank transfer',
        'jazzcash' => 'JazzCash',
        'easypaisa' => 'Easypaisa',
        'upaisa' => 'UPaisa',
        'nayapay' => 'NayaPay',
        'sadapay' => 'SadaPay',
        'online' => 'Online',
    ];

    /** Methods accepted for new payments (without credit). */
    public static function values(): array
    {
        return array_keys(self::MODES);
    }

    /** Methods accepted on a sale: the payment methods and credit. */
    public static function saleValues(): array
    {
        return array_merge(self::values(), ['credit']);
    }

    public static function label(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));
        if (isset(self::MODES[$mode])) return self::MODES[$mode];
        return $mode === 'credit' ? 'Credit' : ($mode === 'cheque' ? 'Cheque' : ucfirst($mode));
    }

    /** <option> tags for a select. */
    public static function options(?string $selected = null, bool $withCredit = false): string
    {
        $list = self::MODES + ($withCredit ? ['credit' => 'Credit'] : []);
        $out = '';
        foreach ($list as $value => $label) {
            $out .= '<option value="' . $value . '"' . ($selected === $value ? ' selected' : '') . '>' . esc($label) . '</option>';
        }
        return $out;
    }
}
