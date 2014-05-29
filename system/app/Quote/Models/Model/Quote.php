<?php
/**
 * Quote model
 *
 * @author iamne Eugene I. Nezhuta <eugene@seotoaster.com>
 */

class Quote_Models_Model_Quote extends Application_Model_Models_Abstract {

	const STATUS_NEW            = 'new';

	const STATUS_SENT           = 'sent';

	const STATUS_SOLD           = 'sold';

	const STATUS_LOST           = 'lost';

	const TEMPLATE_TYPE_QUOTE   = 'typequote';

    const QUOTE_TYPE_AUTO       = 'auto';

	protected $_id              = '';

	protected $_title           = '';

	protected $_status          = self::STATUS_NEW;

	protected $_disclaimer      = '';

    protected $_internalNote      = '';

	protected $_internalMessage = '';

	protected $_cartId          = 0;

	protected $_editedBy        = '';

	protected $_expiresAt       = '';

	protected $_userId          = 0;

	protected $_createdAt       = '';

	protected $_updatedAt       = '';

    protected $_discountTaxRate = 1;

    protected $_deliveryType    = '';

    protected $_creatorId       = '';

    protected $_ownerName       = '';

    protected $_customerName    = '';

    public function setCustomerName($customerName) {
        $this->_customerName = $customerName;
        return $this;
    }

    public function getCustomerName() {
        return $this->_customerName;
    }

    public function setOwnerName($ownerName) {
        $this->_ownerName = $ownerName;
        return $this;
    }

    public function getOwnerName() {
        return $this->_ownerName;
    }

    public function setDiscountTaxRate($discountTaxRate) {
        $this->_discountTaxRate = $discountTaxRate;
    }

    public function getDiscountTaxRate() {
        return $this->_discountTaxRate;
    }

    public function setDeliveryType($deliveryType) {
        $this->_deliveryType = $deliveryType;
    }

    public function getDeliveryType() {
        return $this->_deliveryType;
    }

	public function setCartId($cartId) {
		$this->_cartId = $cartId;
		return $this;
	}

	public function getCartId() {
		return $this->_cartId;
	}

	public function setCreatedAt($createdAt) {
		$this->_createdAt = $createdAt;
		return $this;
	}

	public function getCreatedAt() {
		return $this->_createdAt;
	}

	public function setDisclaimer($disclaimer) {
		$this->_disclaimer = $disclaimer;
		return $this;
	}

	public function getDisclaimer() {
		return $this->_disclaimer;
	}

    public function setInternalNote($internalNote) {
        $this->_internalNote = $internalNote;
        return $this;
    }

    public function getInternalNote() {
        return $this->_internalNote;
    }

	public function setEditedBy($editedBy) {
		$this->_editedBy = $editedBy;
		return $this;
	}

	public function getEditedBy() {
		return $this->_editedBy;
	}

	public function setInternalMessage($internalMessage) {
		$this->_internalMessage = $internalMessage;
		return $this;
	}

	public function getInternalMessage() {
		return $this->_internalMessage;
	}

	public function setStatus($status) {
		$this->_status = $status;
		return $this;
	}

	public function getStatus() {
		return $this->_status;
	}

	public function setTitle($title) {
		$this->_title = $title;
		return $this;
	}

	public function getTitle() {
		return $this->_title;
	}

	public function setUpdatedAt($updatedAt) {
		$this->_updatedAt = $updatedAt;
		return $this;
	}

	public function getUpdatedAt() {
		return $this->_updatedAt;
	}

	public function setUserId($userId) {
		$this->_userId = $userId;
		return $this;
	}

	public function getUserId() {
		return $this->_userId;
	}

	public function setId($id) {
		$this->_id = $id;
		return $this;
	}

	public function getId() {
		return $this->_id;
	}

    public function setExpiresAt($expiresAt) {
        $this->_expiresAt = $expiresAt;
        return $this;
    }

    public function getExpiresAt() {
        return $this->_expiresAt;
    }

    public function setCreatorId($creatorId) {
        $this->_creatorId = $creatorId;
        return $this;
    }

    public function getCreatorId() {
        return $this->_creatorId;
    }
}
