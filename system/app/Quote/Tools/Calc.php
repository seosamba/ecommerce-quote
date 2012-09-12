<?php
/**
 * Calc
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 9/12/12
 * Time: 3:46 PM
 */
class Quote_Tools_Calc {

    const TOTAL_TYPE_GRAND    = 'grand';

    const TOTAL_TYPE_SUB      = 'sub';

    const TOTAL_TYPE_TAX      = 'tax';

    const TOTAL_TYPE_DISCOUNT = 'discount';

    private static $instance  = null;

    /**
     * Cart session attached to the quote
     *
     * @var Models_Model_CartSession
     */
    private $_cart        = null;

    private $_cartContent = null;

    private function __construct() {}

    private function __clone() {}

    public static function getInstance() {
        if(self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(Quote_Models_Model_Quote $quote) {
        $this->_cart        = Quote_Tools_Tools::invokeCart($quote);
        $this->_cartContent = $this->_cart->getCartContent();
        return $this;
    }

    public function calculate($calcType = self::TOTAL_TYPE_GRAND) {
        $total       = 0;
        $cartContent = $this->_cart->getCartContent();
        switch($calcType) {
            case self::TOTAL_TYPE_DISCOUNT:

            break;
            case self::TOTAL_TYPE_GRAND:
                $total = $this->_calculateSubTotal($cartContent) + $this->_calculateTax() + $this->_calculateDiscount();
            break;
            case self::TOTAL_TYPE_SUB:
                $total = $this->_calculateSubTotal($cartContent);
            break;
            case self::TOTAL_TYPE_TAX:
                $total = $this->_calculateTax();
            break;
            default:

            break;
        }
        return $total;
    }

    private function _calculateTax() {
        $total = array_reduce($this->_cartContent, function($result, $item) {
            return ($result += $item['tax']);
        });
    }

    private function _calculateDiscount() {
        return 0;
    }

    private function _calculateSubTotal() {
        return array_reduce($this->_cartContent, function($result, $item) {
            $product        = Models_Mapper_ProductMapper::getInstance()->find($item['product_id']);
            $defaultOptions = $product->getDefaultOptions();
            foreach($item['options'] as $optionId => $selectionId) {
                foreach($defaultOptions as $defaultOption) {
                    if($optionId != $defaultOption['id']) {
                        continue;
                    }
                }
                $selections = array_filter($defaultOption['selection'], function($selection) use($selectionId) {
                    if($selectionId == $selection['id']) {
                        return $selection;
                    }
                });
            }
            if(!empty($selection)) {
                foreach($selections as $selection) {
                    if($selection['priceType'] == 'unit') {
                        $modifier = $selection['priceValue'];
                    } else {
                        $modifier = ($item['tax_price'] / 100) * $selection['priceValue'];
                    }
                    $item['tax_price'] = ($selection['priceSign'] == '+') ? $item['tax_price'] + $modifier : $item['tax_price'] - $modifier;
                }
            }
            return ($result += ($item['tax_price'] * $item['qty']));
        });
    }

}
