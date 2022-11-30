<?php
/*
 * MAGICSPACE: conversionpostpurchasequotecode
 * {conversionpostpurchasequotecode} ... {/conversionpostpurchasequotecode} - conversionpostpurchasequotecode magic space is used to specify place
 * whether display or not conversion code
 */

class MagicSpaces_Conversionpostpurchasequotecode_Conversionpostpurchasequotecode extends Tools_MagicSpaces_Abstract
{

    protected $_parseBefore = true;

    protected function _run()
    {
        $cartContent = Tools_ShoppingCart::getInstance();
        $orderId = $cartContent->getCartId();

        if (!empty($orderId)) {
            $quoteModel = Quote_Models_Mapper_QuoteMapper::getInstance()->findByCartId($orderId);
            if ($quoteModel instanceof Quote_Models_Model_Quote) {
                if (Zend_Registry::isRegistered('postPurchaseCart')) {
                    return $this->_spaceContent;
                }

                $quoteConversionsMapper = Quote_Models_Mapper_QuoteConversionsMapper::getInstance();
                $quoteConversionsModel = $quoteConversionsMapper->findByCartId($orderId);
                if ($quoteConversionsModel instanceof Quote_Models_Model_QuoteConversionsModel) {
                    return '';
                }

                $creatorId = $quoteModel->getCreatorId();
                if (!empty($creatorId)) {
                    $userMapper = Application_Model_Mappers_UserMapper::getInstance();
                    $userModel = $userMapper->find($creatorId);
                    if ($userModel instanceof Application_Model_Models_User) {
                        $quoteCreatorRoleId = $userModel->getRoleId();
                        if ($quoteCreatorRoleId === Tools_Security_Acl::ROLE_SUPERADMIN || $quoteCreatorRoleId === Tools_Security_Acl::ROLE_ADMIN || $quoteCreatorRoleId === Shopping::ROLE_SALESPERSON) {
                            return '';
                        }
                    }
                }

                $quoteConversionsModel = new Quote_Models_Model_QuoteConversionsModel();
                $quoteConversionsModel->setCartId($orderId);
                $quoteConversionsModel->setCreatedAt(Tools_System_Tools::convertDateFromTimezone('now'));
                $quoteConversionsMapper->save($quoteConversionsModel);

                $cart = Models_Mapper_CartSessionMapper::getInstance()->find(
                    $orderId
                );

                if (!$cart instanceof Models_Model_CartSession) {
                    return '';
                }

                $productMapper = Models_Mapper_ProductMapper::getInstance();
                $cartContent = $cart->getCartContent();
                foreach ($cartContent as $key => $product) {
                    $productObject = $productMapper->find($product['product_id']);
                    if ($productObject instanceof Models_Model_Product) {
                        $cartContent[$key]['mpn'] = $productObject->getMpn();
                        $cartContent[$key]['photo'] = $productObject->getPhoto();
                        $cartContent[$key]['productUrl'] = $productObject->getPage()->getUrl();
                        $cartContent[$key]['taxRate'] = Tools_Tax_Tax::calculateProductTax($productObject,
                            null, true);
                    }
                }
                $cart->setCartContent($cartContent);
                $billingAddressId = $cart->getBillingAddressId();
                if (null !== $billingAddressId) {
                    $cart->setBillingAddressId(Tools_ShoppingCart::getAddressById($billingAddressId));
                }
                $shippingAddressId = $cart->getShippingAddressId();
                if (null !== $shippingAddressId) {
                    $cart->setShippingAddressId(Tools_ShoppingCart::getAddressById($shippingAddressId));
                }

                Zend_Registry::set('postPurchaseCart', $cart);
                if ($cart instanceof Models_Model_CartSession && !Zend_Registry::isRegistered('postPurchasePickup') && $cart->getShippingService() === 'pickup') {
                    $pickupLocationConfigMapper = Store_Mapper_PickupLocationConfigMapper::getInstance();
                    $pickupLocationData = $pickupLocationConfigMapper->getCartPickupLocationByCartId($cart->getId());
                    if (empty($pickupLocationData)) {
                        $shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
                        $pickupLocationData = array(
                            'name' => $shoppingConfig['company'],
                            'address1' => $shoppingConfig['address1'],
                            'address2' => $shoppingConfig['address2'],
                            'country' => $shoppingConfig['country'],
                            'city' => $shoppingConfig['city'],
                            'state' => $shoppingConfig['state'],
                            'zip' => $shoppingConfig['zip'],
                            'phone' => $shoppingConfig['phone']
                        );
                    }
                    $pickupLocationData['map_link'] = 'https://maps.google.com/?q=' . $pickupLocationData['address1'] . '+' . $pickupLocationData['city'] . '+' . $pickupLocationData['state'];
                    $pickupLocationData['map_src'] = Tools_Geo::generateStaticGmaps($pickupLocationData,
                        640,
                        300);
                    Zend_Registry::set('postPurchasePickup', $pickupLocationData);
                }

                return $this->_spaceContent;
            }
        }

        return '';

    }

}
