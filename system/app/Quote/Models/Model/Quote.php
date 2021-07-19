<?php
/**
 * Quote model
 *
 * @author iamne Eugene I. Nezhuta <eugene@seotoaster.com>
 */

class Quote_Models_Model_Quote extends Application_Model_Models_Abstract {

	const STATUS_NEW            = 'new';

	const STATUS_SENT           = 'sent';

    const STATUS_SIGNATURE_ONLY_SIGNED    = 'signature_only_signed';

	const STATUS_SOLD           = 'sold';

	const STATUS_LOST           = 'lost';

	const TEMPLATE_TYPE_QUOTE   = 'typequote';

    const QUOTE_TYPE_AUTO       = 'auto';

    const PAYMENT_TYPE_FULL = 'full_payment';

    const PAYMENT_TYPE_FULL_SIGNATURE = 'full_payment_signature';

    const PAYMENT_TYPE_PARTIAL_PAYMENT = 'partial_payment';

    const PAYMENT_TYPE_PARTIAL_PAYMENT_SIGNATURE = 'partial_payment_signature';

    const PAYMENT_TYPE_ONLY_SIGNATURE = 'only_signature';

    public static $_paymentTypesList = array(self::PAYMENT_TYPE_FULL, self::PAYMENT_TYPE_ONLY_SIGNATURE, self::PAYMENT_TYPE_PARTIAL_PAYMENT, self::PAYMENT_TYPE_FULL_SIGNATURE, self::PAYMENT_TYPE_PARTIAL_PAYMENT_SIGNATURE);

	protected $_id              = '';

	protected $_title           = '';

	protected $_status          = self::STATUS_NEW;

	protected $_disclaimer      = '';

    protected $_internalNote      = '';

	protected $_internalMessage = '';

	protected $_cartId          = 0;

	protected $_editedBy        = '';

	protected $_editorId;

	protected $_expiresAt       = '';

	protected $_userId          = 0;

	protected $_createdAt       = '';

	protected $_updatedAt       = '';

    protected $_discountTaxRate = 1;

    protected $_deliveryType    = '';

    protected $_creatorId       = '';

    protected $_ownerName       = '';

    protected $_customerName    = '';

    protected $_paymentType = '';

    protected $_isSignatureRequired = '';

    protected $_pdfTemplate = '';

    protected $_signature = '';

    protected $_isQuoteSigned = '';

    protected $_quoteSignedAt = '';

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

	public function setEditorId($editorId) {
		$this->_editorId = $editorId;
		return $this;
	}

	public function getEditorId() {
		return $this->_editorId;
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

    /**
     * @return string
     */
    public function getPaymentType()
    {
        return $this->_paymentType;
    }

    /**
     * @param string $paymentType
     * @return string
     */
    public function setPaymentType($paymentType)
    {
        $this->_paymentType = $paymentType;

        return $this;
    }

    /**
     * @return string
     */
    public function getIsSignatureRequired()
    {
        return $this->_isSignatureRequired;
    }

    /**
     * @param string $isSignatureRequired
     * @return string
     */
    public function setIsSignatureRequired($isSignatureRequired)
    {
        $this->_isSignatureRequired = $isSignatureRequired;

        return $this;
    }

    /**
     * @return string
     */
    public function getPdfTemplate()
    {
        return $this->_pdfTemplate;
    }

    /**
     * @param string $pdfTemplate
     * @return string
     */
    public function setPdfTemplate($pdfTemplate)
    {
        $this->_pdfTemplate = $pdfTemplate;

        return $this;
    }

    /**
     * @return string
     */
    public function getSignature()
    {
        return $this->_signature;
    }

    /**
     * @param string $signature
     * @return string
     */
    public function setSignature($signature)
    {
        $this->_signature = $signature;

        return $this;
    }

    /**
     * @return string
     */
    public function getIsQuoteSigned()
    {
        return $this->_isQuoteSigned;
    }

    /**
     * @param string $isQuoteSigned
     * @return string
     */
    public function setIsQuoteSigned($isQuoteSigned)
    {
        $this->_isQuoteSigned = $isQuoteSigned;

        return $this;
    }

    /**
     * @return string
     */
    public function getQuoteSignedAt()
    {
        return $this->_quoteSignedAt;
    }

    /**
     * @param string $quoteSignedAt
     * @return string
     */
    public function setQuoteSignedAt($quoteSignedAt)
    {
        $this->_quoteSignedAt = $quoteSignedAt;

        return $this;
    }



}
