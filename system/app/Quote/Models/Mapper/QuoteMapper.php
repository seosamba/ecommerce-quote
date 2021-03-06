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
            'disclaimer'        => $quote->getDisclaimer(),
            'internal_note'     => $quote->getInternalNote(),
			'cart_id'           => $quote->getCartId(),
			'edited_by'         => $quote->getEditedBy(),
            'editor_id'        => $quote->getEditorId(),
            'creator_id'        => $quote->getCreatorId(),
			'expires_at'        => date(Tools_System_Tools::DATE_MYSQL, strtotime($quote->getExpiresAt())),
			'user_id'           => $quote->getUserId(),
			'created_at'        => date(Tools_System_Tools::DATE_MYSQL, strtotime($quote->getCreatedAt())),
			'updated_at'        => date(Tools_System_Tools::DATE_MYSQL, strtotime($quote->getUpdatedAt())),
            'discount_tax_rate' => $quote->getDiscountTaxRate(),
            'delivery_type'     => $quote->getDeliveryType(),
            'payment_type'     => $quote->getPaymentType(),
            'is_signature_required'     => $quote->getIsSignatureRequired(),
            'pdf_template'     => $quote->getPdfTemplate(),
            'signature'     => $quote->getSignature(),
            'is_quote_signed'     => $quote->getIsQuoteSigned(),
            'quote_signed_at'     => $quote->getQuoteSignedAt(),
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

        $quote->registerObserver(new Quote_Tools_GarbageCollector(array(
            'action' => Tools_System_GarbageCollector::CLEAN_ONDELETE
        )));
		$quote->notifyObservers();

		return $deleteResult;
	}

	public function fetchAll($where = null, $order = null, $limit = null, $offset = null, $search = null, $includeCount = false) {
		$entries   = array();
        if($search !== null) {
			$where = ($where === null) ? 'title LIKE "%' . $search .'%" OR cust_addr.email LIKE "%' . $search .'%" OR u1.full_name LIKE "%' . $search .'%" OR cust_addr.lastname LIKE "%' . $search .'%"' : ($where . ' AND (title LIKE "%' . $search .'%" OR cust_addr.email LIKE "%' . $search .'%" OR u1.full_name LIKE "%' . $search .'%" OR cust_addr.lastname LIKE "%' . $search .'%")');
		}
        $table = $this->getDbTable();

        if($includeCount) {
            $select = $table->select()
                ->setIntegrityCheck(false)
                ->from(array('s_q'=>'shopping_quote'))
                ->joinLeft(array('u1'=>'user'), 's_q.user_id=u1.id', '')
                ->joinLeft(array('u2'=>'user'), 's_q.creator_id=u2.id', '')
                ->joinLeft(array('cart'=>'shopping_cart_session'), 's_q.cart_id=cart.id', '')
                ->joinLeft(array('cust_addr'=>'shopping_customer_address'), 'cust_addr.id=cart.billing_address_id', array('cust_addr.firstname', 'cust_addr.lastname'))
                ->columns(array('ownerName' => new Zend_Db_Expr('COALESCE(u2.full_name, u1.full_name)')))
                ->columns(array('clients' => new Zend_Db_Expr('COALESCE(u1.full_name)')));
            ($where) ? $select->where($where) : $select;
            ($order) ? $select->order($order) : $select;

            $result = $table->getAdapter()->fetchAll($select);

            return array(
                'total'  => sizeof($result),
                'data'   => array_slice(array_map(function($item) {
                    $quote = new Quote_Models_Model_Quote($item);
                    $quoteData = $quote->toArray();
                    if (empty($quoteData['creatorId']) && !empty($quoteData['userId'])) {
                        $userLink = Tools_System_Tools::firePluginMethodByPluginName('leads', 'getLeadLink',
                            array($quoteData['userId']), true);
                    } elseif (!empty($quoteData['creatorId'])) {
                        $userLink = Tools_System_Tools::firePluginMethodByPluginName('leads', 'getLeadLink',
                            array($quoteData['userId']), true);
                    } else{
                        $userLink = '';
                    }

                    if (!empty($userLink) && is_array($userLink)) {
                        $userLink = $userLink[$quoteData['userId']];
                    }
                    $quoteData['userLink'] = $userLink;
                    $quoteData['customerName'] = trim($item['firstname'].' '.$item['lastname']);
                    return $quoteData;
                }, $result), $offset, $limit),
                'offset' => $offset,
                'limit'  => $limit
            );

        }

        $result = $table->fetchAll($where, $order, $limit, $offset);

        if(null === $result) {
			return null;
		}

        foreach ($result as $row) {
			$entries[] = new $this->_model($row->toArray());
		}
		return $entries;
	}

	public function getOwnerInfo($quoteId)
    {
        $table = $this->getDbTable();
        $where = $table->getAdapter()->quoteInto('s_q.id = ?', $quoteId);
        $select = $table->select()
            ->setIntegrityCheck(false)
            ->from(array('s_q'=>'shopping_quote'))
            ->joinLeft(array('u1'=>'user'), 's_q.user_id=u1.id', '')
            ->joinLeft(array('u2'=>'user'), 's_q.creator_id=u2.id', '')
            ->columns(array('ownerName' => new Zend_Db_Expr('COALESCE(u1.full_name, u2.full_name)')))
        ->where($where);

        return $table->getAdapter()->fetchRow($select);
    }

    /**
     *
     * @param string $where SQL where clause
     * @param string $order OPTIONAL An SQL ORDER clause.
     * @param int $limit OPTIONAL An SQL LIMIT count.
     * @param int $offset OPTIONAL An SQL LIMIT offset.
     * @param bool $withoutCount without count flag
     * @param bool $singleRecord if true return single record
     * @return array
     */
    public function searchQuotes($where = null, $order = null, $limit = null, $offset = null, $withoutCount = false, $singleRecord = false)
    {
        $select = $this->getDbTable()->getAdapter()->select()
            ->from(array('sq' => 'shopping_quote'),
                array(
                    'sq.id',
                    'sq.title'
                )
            );

        if (!empty($order)) {
            $select->order($order);
        }

        if (!empty($where)) {
            $select->where($where);
        }

        $select->limit($limit, $offset);

        if ($singleRecord === true) {
            $data = $this->getDbTable()->getAdapter()->fetchRow($select);
        } else {
            $data = $this->getDbTable()->getAdapter()->fetchAll($select);
        }

        if ($withoutCount === false) {
            $select->reset(Zend_Db_Select::COLUMNS);
            $select->reset(Zend_Db_Select::FROM);
            $select->reset(Zend_Db_Select::LIMIT_OFFSET);
            $select->reset(Zend_Db_Select::GROUP);

            $select->from(array('sq' => 'shopping_quote'), array('count' => 'COUNT(sq.id)'));
            $count = $this->getDbTable()->getAdapter()->fetchRow($select);

            return array(
                'totalRecords' => $count['count'],
                'data' => $data,
                'offset' => $offset,
                'limit' => $limit
            );
        }

        return $data;
    }


}
