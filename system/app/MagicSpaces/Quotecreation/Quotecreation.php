<?php
/**
 *
 * MAGICSPACE: quotecreation
 * {quotecreation}{/quotecreation} - quote creation date
 *
 * quotecreation:fromdate-2025-12-03
 * quotecreation:todate-2025-12-02
 *
 * Class MagicSpaces_Quotecreation_Quotecreation
 */

class MagicSpaces_Quotecreation_Quotecreation extends Tools_MagicSpaces_Abstract
{


    protected function _run()
    {
        if (empty($this->_params[0])) {
            return '';
        }

        $fromDate = current(preg_grep('/fromdate-*/', $this->_params));
        if (!empty($fromDate)) {
            $fromDateValue = str_replace('fromdate-', '', $fromDate);
        }

        $toDate = current(preg_grep('/todate-*/', $this->_params));
        if (!empty($toDate)) {
            $toDateValue = str_replace('todate-', '', $toDate);
        }

        if (empty($fromDateValue) && empty($toDateValue)) {
            return '';
        }

        $pageHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
        $quote = Quote_Models_Mapper_QuoteMapper::getInstance()->find($pageHelper->clean($this->_toasterData['url']));
        if ($quote instanceof Quote_Models_Model_Quote) {
            $quoteCreationDate = $quote->getCreatedAt();
            if (!empty($fromDateValue)) {
                if (strtotime($quoteCreationDate) >= strtotime($fromDateValue)) {
                    return $this->_spaceContent;
                }

            } elseif (!empty($toDateValue)) {
                if (strtotime($toDateValue) >= strtotime($quoteCreationDate)) {
                    return $this->_spaceContent;
                }
            } else {
                return '';
            }
        }

        return '';

    }

}
