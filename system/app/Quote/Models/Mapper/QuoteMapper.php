<?php
/**
 * Quote mapper
 *
 * @author Eugene I. Nezhuta <eugene@seotoaster.com>
 */

class Quote_Models_Mapper_QuoteMapper extends Application_Model_Mappers_Abstract {

	protected $_dbTable        = 'Quote_Models_DbTable_Quote';

	protected $_model          = 'Quote_Models_Model_Quote';


    /**
     * @param $quote Quote_Models_Model_Quote
     * @return mixed
     * @throws Exceptions_SeotoasterException
     */
    public function save($quote) {
		if(!$quote instanceof Quote_Models_Model_Quote) {
			throw new Exceptions_SeotoasterException('Given parameter should be and Quote_Models_Model_Quote instance');
		}

		$data = array(
			'id'                => $quote->getId(),
			'title'             => $quote->getTitle(),
			'status'            => $quote->getStatus(),
			'cart_id'           => $quote->getCartId(),
			'edited_by'         => $quote->getEditedBy(),
			'expires_at'        => date(DATE_ATOM, strtotime($quote->getExpiresAt())),
			'user_id'           => $quote->getUserId(),
			'created_at'        => date(DATE_ATOM, strtotime($quote->getCreatedAt())),
			'updated_at'        => date(DATE_ATOM, strtotime($quote->getUpdatedAt()))
		);

		$exists = $this->find($quote->getId());
		if($exists) {
			$this->getDbTable()->update($data, array('id=?' => $quote->getId()));
		}
		else {
			$quoteId = $this->getDbTable()->insert($data);
            $quote->setId($quoteId);
		}
		$quote->notifyObservers();
		return $quote;
	}

	public function findByCartId($cartId) {
		return $this->_findWhere($this->getDbTable()->getAdapter()->quoteInto('cart_id = ?', $cartId));
	}

	public function findByUserId($userId) {
		return $this->_findWhere($this->getDbTable()->getAdapter()->quoteInto('user_id = ?', $userId));
	}

	public function findByStatus($status) {
		return $this->_findWhere($this->getDbTable()->getAdapter()->quoteInto("status = ?", $status));
	}

	public function findByTitle($title) {
		return $this->_findWhere($this->getDbTable()->getAdapter()->quoteInto("title = ?", $title));
	}

	public function delete(Quote_Models_Model_Quote $quote) {
		$where        = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $quote->getId());
		$deleteResult = $this->getDbTable()->delete($where);
		$quote->notifyObservers();
		return $deleteResult;
	}

	public function fetchAll($where = null, $order = null, $limit = null, $offset = null, $search = null) {
		$entries   = array();
		if($search !== null) {
			$where = ($where === null) ? 'title LIKE "%' . $search .'%"' : ($where . ' AND title LIKE "%' . $search .'%"');
		}
		$resultSet = $this->getDbTable()->fetchAll($where, $order, $limit, $offset);
		if(null === $resultSet) {
			return null;
		}
		foreach ($resultSet as $row) {
			$entries[] = new $this->_model($row->toArray());
		}
		return $entries;
	}
}
