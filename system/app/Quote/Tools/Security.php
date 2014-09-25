<?php
/**
 * Security
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 9/5/12
 * Time: 1:27 PM
 */
class Quote_Tools_Security {

    public static function isEditAllowed($privew = false) {
        return (Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT) && !$privew);
    }

}
