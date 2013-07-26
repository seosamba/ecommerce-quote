<?php

class Quote_Forms_Quote extends Forms_Address_Abstract {

    const CAPTCHA_SERVICE_CAPTCHA   = 'captcha';

    const CAPTCHA_SERVICE_RECAPTCHA = 'recaptcha';

    private $_captchaService = null;

	public function init() {
		parent::init();

        $this->_captchaService = Quote_Tools_Tools::getValidCaptchaService();

        //initial params and attributes
        $this->setLegend('Billing address')
			->setAttribs(array(
				'id'     => 'plugin-quote-quoteform',
				'class'  => 'toaster-quote',
				'method' => Zend_Form::METHOD_POST
			)
        );

        //only necessary decorators
		$this->setDecorators(array('FormElements', 'Form'));

		// setting required fields
		$this->_setRequired(array(
            $this->getElement('firstname'),
            $this->getElement('email')
        ));

        // clear some validators
        $this->getElement('state')->setRegisterInArrayValidator(false);
        $this->getElement('country')->setRegisterInArrayValidator(false);
        $this->getElement('state')->clearValidators();

		$this->addElement(new Zend_Form_Element_Textarea(array(
            'name'  => 'disclaimer',
            'label' => 'Notes',
            'rows'  => '3'
        )));

        $this->addElement(new Zend_Form_Element_Checkbox(array(
			'name'  => 'sameForShipping',
			'id'    => 'same-for-shipping',
			'label' => 'Use same data for shipping?',
		)));

        if($this->_captchaService) {
            $this->addElement($this->_generateCaptchaElement());
        }

        //adding display groups
        $this->addDisplayGroups(array(
			'leftColumn'  => array('firstname', 'lastname', 'company', 'email', 'address1', 'address2'),
			'rightColumn' => array('country', 'city', 'state', 'zip', 'phone', 'disclaimer', 'sameForShipping', 'captcha')
		));

        //set display groups decorators
		$this->getDisplayGroup('leftColumn')->setDecorators(array('FormElements', 'Fieldset'));
		$this->getDisplayGroup('rightColumn')->setDecorators(array('FormElements', 'Fieldset'));

        $this->addElement(new Zend_Form_Element_Hidden(array(
            'name'  => 'productId',
            'value' => ''
        )));

        $this->addElement(new Zend_Form_Element_Hidden(array(
            'name'  => 'productOptions',
            'value' => ''
        )));

		$this->_applyDecorators();

		$this->addElement(new Zend_Form_Element_Submit(array(
			'name'   => 'sendQuote',
			'id'     => 'send-quote',
			'label'  => 'Send me a quote',
			'ignore' => true
		)));
	}

    private function _generateCaptchaElement() {
        $captcha = null;
        if($this->_captchaService == self::CAPTCHA_SERVICE_RECAPTCHA) {
            $websiteConfig    = Zend_Controller_Action_HelperBroker::getStaticHelper('config')->getConfig();
            $recaptchaService =  new Zend_Service_ReCaptcha($websiteConfig['recapthaPublicKey'], $websiteConfig['recapthaPrivateKey']);
            $captcha          = new Zend_Form_Element_Captcha('captcha', array(
                'captcha'        => 'ReCaptcha',
                'captchaOptions' => array('captcha' => 'ReCaptcha', 'service' => $recaptchaService, 'theme' => 'custom'),
                'disableLoadDefaultDecorators' => true,
                'decorators' => array(
                    'Captcha_ReCaptcha',
                    array(
                        'ViewScript',
                        array(
                            'viewScript' => 'backend/form/recaptcha.phtml',
                            'placement' => false,
                        ),
                    ),
                )
            ));
        }
        if($this->_captchaService == self::CAPTCHA_SERVICE_CAPTCHA) {
            $websiteHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('website');
            $captcha = new Zend_Form_Element_Captcha('captcha', array(
                'label'   => '',
                'captcha' => array(
                    'captcha' => 'Image',
                    'name'    => 'captcha',
                    'wordLen' => 5,
                    'height'  => 45,
                    'timeout' => 300,
                    'dotNoiseLevel' => 0,
                    'LineNoiseLevel' => 0,
                    'font'    => $websiteHelper->getPath() . 'system/fonts/Alcohole.ttf',
                    'imgDir'  => $websiteHelper->getPath() . $websiteHelper->getTmp(),
                    'imgUrl'  => $websiteHelper->getUrl() . $websiteHelper->getTmp()
                )
            ));

        }
        return $captcha;
    }

    private function _applyDecorators() {
        $this->setElementDecorators(array(
            'ViewHelper',
            'Label',
            array('HtmlTag', array('tag' => 'div'))
        ), array(
            'captcha',
            'productId',
            'productOptions'
        ), false);
    }

    private function _setRequired(array $elements) {
        array_walk($elements, function($element) {
            $element->setRequired(true)
                ->setAttribs(array('class' => 'quote-required required'));
        });
    }

    /**
     * Set captcha service to use
     *
     * @param string $captchaService
     * @return Quote_Forms_Quote
     */
    public function setCaptchaService($captchaService) {
        $this->_captchaService = $captchaService;
        return $this;
    }



}
