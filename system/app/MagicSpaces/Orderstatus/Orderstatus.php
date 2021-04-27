<?php

/**
 * MAGICSPACE: orderstatus
 * {orderstatus} ... {/orderstatus} - Orderstatus magicspace displays content for customers
 *
 * Class MagicSpaces_Orderstatus_Orderstatus
 */
class MagicSpaces_Orderstatus_Orderstatus extends Tools_MagicSpaces_Abstract
{
    /**
     * Order status Magic Space
     * {orderstatus[:partial,completed,...]}
     * Here you can put content that will be displayed if status match
     * {/orderstatus}
     * @return string
     */
    protected function _run()
    {

        $finalResult = '';
        $result = $this->_spaceContent;

        $statuses = explode(',', filter_var($this->_params[0], FILTER_SANITIZE_STRING));

        if (empty($statuses)) {
            return '';
        }

        $mapper = Quote_Models_Mapper_QuoteMapper::getInstance();
        $requestedUri = Tools_System_Tools::getRequestUri();
        $quote = $mapper->find(
            Zend_Controller_Action_HelperBroker::getStaticHelper('page')->clean($requestedUri)
        );

        if ($quote instanceof Quote_Models_Model_Quote) {
            $cartSessionMapper = Models_Mapper_CartSessionMapper::getInstance();
            $cartSessionModel = $cartSessionMapper->find($quote->getCartId());
            if ($cartSessionModel instanceof Models_Model_CartSession) {
                $status = $cartSessionModel->getStatus();
                if (in_array($status, $statuses)) {
                    return $result;
                }
            }
        }


        return $finalResult;
    }

}
