<?php
/**
 * User: iamne Eugene I. Nezhuta <eugene@seotoaster.com>
 * Date: 4/2/12
 * Time: 1:17 PM
 */

class Quote_Models_DbTable_ShoppingCustomerAddress extends Zend_Db_Table_Abstract {

	protected $_name = 'shopping_customer_address';

	public function searchAddress($searchTerm) {
        $search              = array();
        $search['firstname'] = $searchTerm;
        $search['lastname']  = $searchTerm;
        $search['email']     = $searchTerm;

        preg_match("/[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})/i", $searchTerm, $email);
        if (isset($email[0]) && $email[0] != '') {
            $searchTerm      = trim(str_replace($email[0], '', $searchTerm), ' ');
            $search['email'] = $email[0];
        }

        $arraySearchTerm = explode(' ', preg_replace('/ {2,}/', ' ', $searchTerm));
        $select          = $this->select()->from($this->_name);

        // Email
        if (isset($email[0]) && $search['email'] == $email[0] && $searchTerm == '') {
            $select->where('email LIKE ?', '%'.$search['email'].'%');
        }
        // Email and firstname or lastname
        elseif (isset($email[0]) && $search['email'] == $email[0] && sizeof($arraySearchTerm) == 1) {
            $select->where('firstname LIKE ?', '%'.$searchTerm.'%')
                ->where('email LIKE ?', '%'.$search['email'].'%')
                ->orWhere('lastname LIKE ?', '%'.$searchTerm.'%')
                ->where('email LIKE ?', '%'.$search['email'].'%');
        }
        // Uncertain parameter
        elseif (sizeof($arraySearchTerm) == 1) {
            $select->where('firstname LIKE ?', '%'.$searchTerm.'%')
                ->orWhere('lastname LIKE ?', '%'.$searchTerm.'%')
                ->orWhere('email LIKE ?', '%'.$searchTerm.'%');
        }
        // Firstname or lastname and email
        else {
            $search['firstname'] = $arraySearchTerm[0];
            $search['lastname']  = $arraySearchTerm[1];
            $select->where('firstname LIKE ?', '%'.$search['firstname'].'%')
                ->where('lastname LIKE ?', '%'.$search['lastname'].'%');

            if (isset($email[0]) && $search['email'] == $email[0]) {
                $select->where('email LIKE ?', '%'.$search['email'].'%');
            }

            $select->orWhere('firstname LIKE ?', '%'.$search['lastname'].'%')
                ->where('lastname LIKE ?', '%'.$search['firstname'].'%');

            if (isset($email[0]) && $search['email'] == $email[0]) {
                $select->where('email LIKE ?', '%'.$search['email'].'%');
            }
        }

        $select->order('user_id');

		return $this->fetchAll($select)->toArray();
	}
}
