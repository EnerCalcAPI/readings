<?php
 
 namespace Enercalcapi\Readings\Http\Controllers;
 
use Illuminate\Contracts\Validation\Rule;
 
class EAN implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
 
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return self::isEAN($value);
    }
 
    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Invalid EAN13 or EAN18';
    }
 
    /**
     * Checks if a value oblies to the rules of EAN13 or EAN18
     *
     * @param string $value
     * @return boolean
     */
    public static function isEAN(string $value): bool
    {
        return self::isEAN13($value) || self::isEAN18($value);
    }
 
    /**
     * Checks if a value oblies to the rules of EAN13
     *
     * @param string $value
     * @return boolean
     */
    public static function isEAN13(string $value): bool
    {
        return
            strlen($value) == 13 &&
            self::validChecksum($value);
    }
 
    /**
     * Checks if a value oblies to the rules of EAN13
     *
     * @param string $value
     * @return boolean
     */
    public static function isEAN18(string $value): bool
    {
        return
            strlen($value) == 18 &&
            self::validChecksum($value);
    }
 
    /**
     * Checks if a value oblies to the rules of EAN
     *
     * @param string $value
     * @return boolean
     */
    protected static function validChecksum(string $value): bool
    {
        if (is_int($value)) {
            $value = '' . $value;
        }
        list($weightA, $weightB) = self::getWeights(strlen($value));
        $checksum = 0;
        for ($i = strlen($value) - 2; $i >= 0; $i--) {
            $weight = ($i % 2 ? $weightA : $weightB);
            $checksum += ($weight * $value[$i]);
        }
        $checksum = (10 - ($checksum % 10)) % 10;
        return intval($value[strlen($value) - 1]) == $checksum;
    }
 
    protected static function getWeights($length): array
    {
        switch ($length) {
            case 13:
                return [3, 1];
            case 18:
                return [1, 3];
        }
        return [];
    }
}