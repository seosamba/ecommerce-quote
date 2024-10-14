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
			'id'                              => $quote->getId(),
			'title'                           => $quote->getTitle(),
			'status'                          => $quote->getStatus(),
            'disclaimer'                      => $quote->getDisclaimer(),
            'internal_note'                   => $quote->getInternalNote(),
			'cart_id'                         => $quote->getCartId(),
			'edited_by'                       => $quote->getEditedBy(),
            'editor_id'                       => $quote->getEditorId(),
            'creator_id'                      => $quote->getCreatorId(),
			'expires_at'                      => date('Y-m-d', strtotime($quote->getExpiresAt())),
			'expiration_notification_is_send' => $quote->getExpirationNotificationIsSend(),
			'user_id'                         => $quote->getUserId(),
			'created_at'                      => date(Tools_System_Tools::DATE_MYSQL, strtotime($quote->getCreatedAt())),
			'updated_at'                      => date(Tools_System_Tools::DATE_MYSQL, strtotime($quote->getUpdatedAt())),
            'discount_tax_rate'               => $quote->getDiscountTaxRate(),
            'delivery_type'                   => $quote->getDeliveryType(),
            'payment_type'                    => $quote->getPaymentType(),
            'is_signature_required'           => $quote->getIsSignatureRequired(),
            'pdf_template'                    => $quote->getPdfTemplate(),
            'signature'                       => $quote->getSignature(),
            'is_quote_signed'                 => $quote->getIsQuoteSigned(),
            'quote_signed_at'                 => $quote->getQuoteSignedAt(),
            'is_quote_restricted_control'     => $quote->getIsQuoteRestrictedControl(),
            'signature_info_field'            => $quote->getSignatureInfoField(),
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

    /**
     * Get quotes data with info
     *
     * @param string $where SQL where clause
     * @param string $order OPTIONAL An SQL ORDER clause.
     * @param int $limit OPTIONAL An SQL LIMIT count.
     * @param int $offset OPTIONAL An SQL LIMIT offset.
     * @param string $search search string
     * @param bool $withoutCount flag to get with or without records quantity
     * @param bool $singleRecord flag fetch single record
     *
     * @return array
     */
    public function fetchAllData($where = null, $order = null, $limit = null, $offset = null, $search = null, $withoutCount = false, $singleRecord = false)
    {

        $additionalParamsForSelect = array(
            'ownerName' => new Zend_Db_Expr('COALESCE(u2.full_name, u1.full_name)'),
            'clients' => new Zend_Db_Expr('COALESCE(u1.full_name)')
        );

        if ($search !== null) {
            $deepSearch = explode(' ', $search);
            $searchTitleWhere = '';
            $searchFullNameWhere = '';
            $searchFullName2Where = '';
            $searchLastNameWhere = '';

            foreach ($deepSearch as $searchParam) {
                if (!empty($searchTitleWhere)) {
                    $searchTitleWhere .= ' AND ';
                }
                $searchTitleWhere .= 's_q.title LIKE "%' . $searchParam . '%"';

                if (!empty($searchFullNameWhere)) {
                    $searchFullNameWhere .= ' AND ';
                    $searchFullName2Where .= ' AND ';
                }
                $searchFullNameWhere .= 'u1.full_name LIKE "%' . $searchParam . '%"';
                $searchFullName2Where .= 'u2.full_name LIKE "%' . $searchParam . '%"';

                if (!empty($searchLastNameWhere)) {
                    $searchLastNameWhere .= ' AND ';
                }
                $searchLastNameWhere .= 'cust_addr.lastname LIKE "%' . $searchParam . '%"';
            }

            $searchTitleWhere = '(' . $searchTitleWhere . ')';
            $searchFullNameWhere = '(' . $searchFullNameWhere . ' OR ' . $searchFullName2Where . ')';
            $searchLastNameWhere = '(' . $searchLastNameWhere . ')';

            if ($where === null) {
                $where = $searchTitleWhere . ' OR cust_addr.email LIKE "%' . $search . '%" OR ' . $searchFullNameWhere . ' OR ' . $searchLastNameWhere;
            } else {
                $where = ($where . ' AND (' . $searchTitleWhere . ' OR cust_addr.email LIKE "%' . $search . '%" OR ' . $searchFullNameWhere . ' OR ' . $searchLastNameWhere . ')');
            }
        }

        $params = array(
            'id' => 's_q.id',
            'title' => 's_q.title',
            'status' => 's_q.status',
            'disclaimer' => 's_q.disclaimer',
            'internalNote' => 's_q.internal_note',
            'discountTaxRate' => 's_q.discount_tax_rate',
            'deliveryType' => 's_q.delivery_type',
            'cartId' => 's_q.cart_id',
            'editedBy' => 's_q.edited_by',
            'editorId' => 's_q.editor_id',
            'creatorId' => 's_q.creator_id',
            'expiresAt' => 's_q.expires_at',
            'expirationNotificationIsSend' => 's_q.expiration_notification_is_send',
            'userId' => 's_q.user_id',
            'createdAt' => 's_q.created_at',
            'updatedAt' => 's_q.updated_at',
            'paymentType' => 's_q.payment_type',
            'isSignatureRequired' => 's_q.is_signature_required',
            'pdfTemplate' => 's_q.pdf_template',
            'signature' => 's_q.signature',
            'isQuoteSigned' => 's_q.is_quote_signed',
            'quoteSignedAt' => 's_q.quote_signed_at',
            'isQuoteRestrictedControl' => 's_q.is_quote_restricted_control',
            'signatureInfoField' => 's_q.signature_info_field',
            'cartStatus' => 'cart.status',
            'cust_addr.firstname',
            'cust_addr.lastname'
        );

        $params = array_merge($additionalParamsForSelect, $params);

        $select = $this->getDbTable()->getAdapter()->select()
            ->from(array('s_q' => 'shopping_quote'),
                $params
            )
            ->joinLeft(array('u1' => 'user'), 's_q.user_id=u1.id', array())
            ->joinLeft(array('u2' => 'user'), 's_q.creator_id=u2.id', array())
            ->joinLeft(array('cart' => 'shopping_cart_session'), 's_q.cart_id=cart.id', array())
            ->joinLeft(array('cust_addr' => 'shopping_customer_address'), 'cust_addr.id=cart.billing_address_id', array());

        if (!empty($order)) {
            $select->order($order);
        }

        if (!empty($where)) {
            $select->where($where);
        }

        $select->limit($limit, $offset);

        if ($singleRecord) {
            $data = $this->getDbTable()->getAdapter()->fetchRow($select);
        } else {
            $data = $this->getDbTable()->getAdapter()->fetchAll($select);
        }

        if (!empty($data)) {
            $data = $this->_addAdditionalQuoteInfo($data);
        }

        if ($withoutCount === false) {
            $select->reset(Zend_Db_Select::COLUMNS);
            $select->reset(Zend_Db_Select::FROM);
            $select->reset(Zend_Db_Select::LIMIT_OFFSET);
            $select->reset(Zend_Db_Select::LIMIT_COUNT);

            $count = array('count' => new Zend_Db_Expr('COUNT(DISTINCT(s_q.id))'));
            $count = array_merge($count, $additionalParamsForSelect);

            $select->from(array('s_q' => 'shopping_quote'),
                    $count
                )
                ->joinLeft(array('u1' => 'user'), 's_q.user_id=u1.id', array())
                ->joinLeft(array('u2' => 'user'), 's_q.creator_id=u2.id', array())
                ->joinLeft(array('cart' => 'shopping_cart_session'), 's_q.cart_id=cart.id', array())
                ->joinLeft(array('cust_addr' => 'shopping_customer_address'), 'cust_addr.id=cart.billing_address_id', array());

            $select = $this->getDbTable()->getAdapter()->select()
                ->from(
                    array('subres' => $select),
                    array('count' => 'SUM(count)')
                );

            $count = $this->getDbTable()->getAdapter()->fetchRow($select);

            return array(
                'total' => $count['count'],
                'data' => $data,
                'offset' => $offset,
                'limit' => $limit
            );
        } else {
            return $data;
        }

    }

    /**
     * Backward compatibility function
     *
     * @param $data
     * @return array[]
     * @throws Zend_Reflection_Exception
     */
    private function _addAdditionalQuoteInfo($data)
    {
        return array_map(function ($item) {
            $quote = new Quote_Models_Model_Quote($item);
            $quoteData = $quote->toArray();
            if (empty($quoteData['creatorId']) && !empty($quoteData['userId'])) {
                $userLink = Tools_System_Tools::firePluginMethodByPluginName('leads', 'getLeadLink',
                    array($quoteData['userId']), true);
            } elseif (!empty($quoteData['creatorId'])) {
                $userLink = Tools_System_Tools::firePluginMethodByPluginName('leads', 'getLeadLink',
                    array($quoteData['userId']), true);
            } else {
                $userLink = '';
            }

            if (!empty($userLink) && is_array($userLink)) {
                $userLink = $userLink[$quoteData['userId']];
            }
            $quoteData['userLink'] = $userLink;
            $quoteData['customerName'] = trim($item['firstname'] . ' ' . $item['lastname']);
            $quoteData['cartStatus'] = $item['cartStatus'];
            return $quoteData;
        }, $data);
    }

	public function fetchAll($where = null, $order = null, $limit = null, $offset = null, $search = null, $includeCount = false) {
		$entries   = array();
        if($search !== null) {
            $deepSearch = explode(' ', $search);
            $searchTitleWhere = '';
            $searchFullNameWhere = '';
            $searchFullName2Where = '';
            $searchLastNameWhere = '';

            foreach ($deepSearch as $searchParam) {
                if(!empty($searchTitleWhere)) {
                    $searchTitleWhere .= ' AND ';
                }
                $searchTitleWhere .= 'title LIKE "%' . $searchParam . '%"';

                if(!empty($searchFullNameWhere)) {
                    $searchFullNameWhere .= ' AND ';
                    $searchFullName2Where .= ' AND ';
                }
                $searchFullNameWhere.= 'u1.full_name LIKE "%' . $searchParam . '%"';
                $searchFullName2Where.= 'u2.full_name LIKE "%' . $searchParam . '%"';

                if(!empty($searchLastNameWhere)) {
                    $searchLastNameWhere .= ' AND ';
                }
                $searchLastNameWhere .= 'cust_addr.lastname LIKE "%' . $searchParam . '%"';
            }

            $searchTitleWhere = '('. $searchTitleWhere . ')';
            //$searchFullNameWhere = '('. $searchFullNameWhere . ')';
            $searchFullNameWhere = '('. $searchFullNameWhere . ' OR '. $searchFullName2Where .')';
            $searchLastNameWhere = '('. $searchLastNameWhere . ')';

            //$where = ($where === null) ? 'title LIKE "%' . $search .'%" OR cust_addr.email LIKE "%' . $search .'%" OR u1.full_name LIKE "%' . $search .'%" OR cust_addr.lastname LIKE "%' . $search .'%"' : ($where . ' AND (title LIKE "%' . $search .'%" OR cust_addr.email LIKE "%' . $search .'%" OR u1.full_name LIKE "%' . $search .'%" OR cust_addr.lastname LIKE "%' . $search .'%")');
            if($where === null) {
                $where = $searchTitleWhere . ' OR cust_addr.email LIKE "%'. $search . '%" OR ' . $searchFullNameWhere . ' OR ' . $searchLastNameWhere;
            } else {
                $where = ($where . ' AND (' . $searchTitleWhere . ' OR cust_addr.email LIKE "%'. $search . '%" OR ' . $searchFullNameWhere . ' OR ' . $searchLastNameWhere . ')');
            }
		}
        $table = $this->getDbTable();

        if($includeCount) {
            $select = $table->select()
                ->setIntegrityCheck(false)
                ->from(array('s_q'=>'shopping_quote'))
                ->joinLeft(array('u1'=>'user'), 's_q.user_id=u1.id', '')
                ->joinLeft(array('u2'=>'user'), 's_q.creator_id=u2.id', '')
                ->joinLeft(array('cart'=>'shopping_cart_session'), 's_q.cart_id=cart.id', array('cartStatus'=> 'cart.status'))
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
                    $quoteData['cartStatus'] = $item['cartStatus'];
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

    /**
     * Update creator id
     *
     * @param int $oldCreatorId old creator id
     * @param int $newCreatorId new creator id
     * @throws Exception
     */
    public function updateCreatorId($oldCreatorId, $newCreatorId)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('creator_id = ?', $oldCreatorId);
        $data = array('creator_id' => $newCreatorId);
        $this->getDbTable()->getAdapter()->update('shopping_quote', $data, $where);
    }

    /**
     * Get users info
     * @param bool $pairs return result in pairs key => value
     * @param bool $adminGroup admin group user only flag
     * @param string $order OPTIONAL An SQL ORDER clause.
     * @return array
     */
    public function getAllUsers($pairs = false, $adminGroup = false, $order = null)
    {
        $select = $this->getDbTable()->getAdapter()->select()->from(array('u' => 'user'),
            array('id', 'full_name'));

        if(!empty($order)) {
            $select->order($order);
        }

        if ($adminGroup === true) {
            $where = $this->getDbTable()->getAdapter()->quoteInto('role_id IN (?)',
                array(Tools_Security_Acl::ROLE_SUPERADMIN,
                    Tools_Security_Acl::ROLE_ADMIN,
                    Shopping::ROLE_SALESPERSON
                )
            );
            $select->where($where);
        }

        if ($pairs ===true) {
            return $this->getDbTable()->getAdapter()->fetchPairs($select);
        } else {
            return $this->getDbTable()->getAdapter()->fetchAssoc($select);
        }
    }

    /**
     * @param $expiredAt
     * @return mixed
     * @throws Exception
     */
    public function fetchQuotesToNotify($expiredAt) {
        $where = $this->getDbTable()->getAdapter()->quoteInto('expiration_notification_is_send = ?', '0');
        $where .= ' AND ' . $this->getDbTable()->getAdapter()->quoteInto('scs.status IN (?)', array(
            Models_Model_CartSession::CART_STATUS_NEW,
            Models_Model_CartSession::CART_STATUS_PROCESSING,
            Models_Model_CartSession::CART_STATUS_PENDING
        ));

        $where  .= ' AND ' . $this->getDbTable()->getAdapter()->quoteInto('sq.is_quote_signed = ?', '0');

        if(!empty($expiredAt)) {
            $where .= ' AND ' . $this->getDbTable()->getAdapter()->quoteInto('sq.expires_at <= ?', $expiredAt);
        }

        $select = $this->getDbTable()->getAdapter()->select()->from(array('sq'=>'shopping_quote'), array(
            'quoteId' => 'sq.id',
            'sq.expires_at',
            'userEmail' => 'u.email',
            'userFullName' => 'u.full_name',
            'userMobileCountryCode' => 'u.mobile_country_code_value',
            'userMobilePhone' => 'u.mobile_phone',
            'userDesctopCountryCode' => 'u.desktop_country_code_value',
            'userDesctopPhone' => 'u.desktop_phone',
            'shippingAddressId' => 'scs.shipping_address_id',
            'billingAddressId' => 'scs.billing_address_id',
            'cartId' => 'scs.id',
        ))->joinLeft(array('scs'=>'shopping_cart_session'), 'sq.cart_id=scs.id', '')
            ->joinLeft(array('u'=>'user'), 'sq.user_id=u.id', '')
            ->joinLeft(array('s_adr' => 'shopping_customer_address'), 's_adr.id = scs.shipping_address_id', array(
                'shipping_email' => 'email',
                'shipping_phone_country_code_value' => 'phone_country_code_value',
                'shipping_phone' => 'phone',
                'shipping_mobile_country_code_value' => 'mobile_country_code_value',
                'shipping_mobile' => 'mobile',
                'shipping_firstname' => 'firstname',
                'shipping_lastname' => 'lastname'
            ))
            ->joinLeft(array('b_adr' => 'shopping_customer_address'), 'b_adr.id = scs.billing_address_id', array(
                'billing_email' => 'email',
                'billing_phone_country_code_value' => 'phone_country_code_value',
                'billing_phone' => 'phone',
                'billing_mobile_country_code_value' => 'mobile_country_code_value',
                'billing_mobile' => 'mobile',
                'billing_firstname' => 'firstname',
                'billing_lastname' => 'lastname'
            ))
            ->where($where);

        return $this->getDbTable()->getAdapter()->fetchAll($select);

    }

    /**
     * Get all possible owners of the quotes
     * @param int $excludeId exclude owner id
     * @param array $ids user ids
     * @param bool $fullInfo full info
     * @return array
     */
    public function getOwnersFullList($excludeId = 0, $ids = array(), $fullInfo = false)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('role_id IN (?)',
            array(Tools_Security_Acl::ROLE_ADMIN, Shopping::ROLE_SALESPERSON, Tools_Security_Acl::ROLE_SUPERADMIN));
        if ($excludeId) {
            $where .= ' AND ' . $this->getDbTable()->getAdapter()->quoteInto('id <> ?', $excludeId);
        }

        if (!empty($ids)) {
            $where .= ' AND ' . $this->getDbTable()->getAdapter()->quoteInto('id IN (?)', $ids);
        }

        if (!empty($fullInfo)) {
            $select = $this->getDbTable()->getAdapter()->select()->from(array('u' => 'user'),
                array('id', 'full_name', 'role_id', 'email'))->where($where);
            return $this->getDbTable()->getAdapter()->fetchAssoc($select);

        } else {
            $select = $this->getDbTable()->getAdapter()->select()->from(array('u' => 'user'),
                array('id', 'full_name'))->where($where);
            return $this->getDbTable()->getAdapter()->fetchPairs($select);
        }

    }


}
