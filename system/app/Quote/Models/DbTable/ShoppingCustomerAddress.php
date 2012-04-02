<?php
/**
 * User: iamne Eugene I. Nezhuta <eugene@seotoaster.com>
 * Date: 4/2/12
 * Time: 1:17 PM
 */

class Quote_Models_DbTable_ShoppingCustomerAddress extends Zend_Db_Table_Abstract {

	protected $_name = 'shopping_customer_address';

	public function searchAddress($searchTerm) {
		$select = $this->select()
			->from($this->_name)
			->where('firstname LIKE ?', '%' . $searchTerm . '%')
			->orWhere('lastname LIKE ?', '%' . $searchTerm . '%')
			->orWhere('email LIKE ?', '%' . $searchTerm . '%')
			->order('user_id');
		return $this->fetchAll($select)->toArray();
	}
}
