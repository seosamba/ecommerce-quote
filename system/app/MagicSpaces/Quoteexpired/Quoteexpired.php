<?php
/**
 *
 * MAGICSPACE: quoteexpired
 * {quoteexpired}{/quoteexpired} - display content if quote have status lost
 * {quoteexpired:not}{/quoteexpired} - display content if quote doesn't have status lost
 *
 * Class MagicSpaces_Quoteexpired_Quoteexpired
 */

class MagicSpaces_Quoteexpired_Quoteexpired extends Tools_MagicSpaces_Abstract {

    private $_expired = true;
    private $_quoteLost  = false;

	protected function _run() {
       $this->_parseParams();
       $pageHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
       $quote       = Quote_Models_Mapper_QuoteMapper::getInstance()->find($pageHelper->clean($this->_toasterData['url']));
       if($quote instanceof Quote_Models_Model_Quote){
           if($quote->getStatus() === Quote_Models_Model_Quote::STATUS_LOST){
               $this->_quoteLost = true;
           }
           if($this->_expired && $this->_quoteLost){
               return $this->_spaceContent;
           }elseif(!$this->_expired && !$this->_quoteLost){
               return $this->_spaceContent;
           }
           return '';
       }
       return '';

    }

    /**
     * Parse magic space parameters $_params
     *
     */
    private function _parseParams() {
        if (is_array($this->_params)) {
            foreach ($this->_params as $key => $param) {
                if (empty($param)) {
                    continue;
                } else {
                    if ($param === 'not') {
                        $this->_expired = false;
                        continue;
                    }
                }
            }
        }

    }

}
