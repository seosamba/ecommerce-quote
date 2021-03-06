<?php

class Quote_Forms_Quote extends Forms_Address_Abstract {

	public function init() {
		parent::init();

        $translator =  Zend_Registry::get('Zend_Translate');

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

        $this->addElement(new Zend_Form_Element_Select(array(
            'name'         => 'prefix',
            'id'           => 'prefix',
            'label'        => $translator->translate('Prefix'),
            'value'        => $this->_prefix,
            'multiOptions' => array('' => $translator->translate('Select')) + Tools_System_Tools::getAllowedPrefixesList()
        )));

		// setting required fields
		$this->_setRequired(array(
            $this->getElement('firstname'),
            $this->getElement('email')
        ));

        $this->getElement('phonecountrycode')->setLabel('Phone');
        $this->getElement('phone')->setLabel(null);

        // clear some validators
        $this->getElement('state')->setRegisterInArrayValidator(false);
        $this->getElement('country')->setRegisterInArrayValidator(false);
        $this->getElement('state')->clearValidators();

        // change email field id for compatibility with other forms
        $this->getElement('email')->setAttrib('id', 'quote-form-email');

		$this->addElement(new Zend_Form_Element_Textarea(array(
            'name'  => 'disclaimer',
            'label' => $translator->translate('Notes'),
            'rows'  => '3'
        )));

        $this->addElement(new Zend_Form_Element_Checkbox(array(
			'name'  => 'sameForShipping',
			'id'    => 'same-for-shipping',
			'label' => $translator->translate('Use same data for shipping?'),
		)));

        $this->addElement(new Zend_Form_Element_Text(array(
            'name'  => 'position',
            'label' => $translator->translate('Position'),
            'rows'  => '3'
        )));


        //adding display groups
        $this->addDisplayGroups(array(
			'leftColumn'  => array('prefix', 'firstname', 'lastname', 'company', 'position','email', 'address1', 'address2'),
			'rightColumn' => array('country', 'city', 'state', 'zip', 'disclaimer')
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

		$this->addElement(new Zend_Form_Element_Submit(array(
			'name'   => 'sendQuote',
			'id'     => 'send-quote',
			'label'  => $translator->translate('Send me a quote'),
            'decorators' => array(
                'ViewHelper',
                array('HtmlTag', array('tag' => 'p'))
            ),
			'ignore' => true
		)));

        $this->addElement(new Zend_Form_Element_Select(array(
            'name'         => 'mobilecountrycode',
            'label'        => $translator->translate('Mobile'),
            'multiOptions' => Tools_System_Tools::getFullCountryPhoneCodesList(true, array(), true),
            'value'        => Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('country'),
            'style'        => 'width: 41.667%;',
        )));

        $this->addElement(new Zend_Form_Element_Text(array(
            'name'     => 'mobile',
            'label'    => null,
            'value'    => '',
            'style'    => 'width: 58.333%;',
         )));

        $this->_applyDecorators();
        $this->getElement('sendQuote')->removeDecorator('Label');
        $this->getElement('sendQuote')->removeDecorator('HtmlTag');
	}

    private function _applyDecorators() {
        $hiddenElements = array(
            'productId',
            'productOptions'
        );

        $this->setElementDecorators(array(
            'ViewHelper',
            'Label',
            array('HtmlTag', array('tag' => 'p'))
        ), array('captcha'), false);

        // remove decorator html tag from hidden elements
        foreach($hiddenElements as $element) {
            $this->getElement($element)->removeDecorator('HtmlTag');
        }

        $this->getElement('phone')->removeDecorator('HtmlTag');
        $this->getElement('phonecountrycode')->removeDecorator('HtmlTag');
        $this->getElement('mobile')->removeDecorator('HtmlTag');
        $this->getElement('mobilecountrycode')->removeDecorator('HtmlTag');

        $this->addDisplayGroup(array(
            'mobilecountrycode',
            'mobile'
        ),'mobilesBlock',array('HtmlTag', array('tag' => 'div')));

        $this->addDisplayGroup(array(
            'phonecountrycode',
            'phone'
        ),'phonesBlock',array('HtmlTag', array('tag' => 'div')));

        $mobilesBlock = $this->getDisplayGroup('mobilesBlock');
        $mobilesBlock->setDecorators(array(
            'FormElements',
            array('HtmlTag',array('tag'=>'p', 'class' => 'mobile-desktop-phone-block'))
        ));

        $phonesBlock = $this->getDisplayGroup('phonesBlock');
        $phonesBlock->setDecorators(array(
            'FormElements',
            array('HtmlTag',array('tag'=>'p', 'class' => 'mobile-desktop-phone-block'))
        ));

        $this->addDisplayGroup(array(
            'sameForShipping'
        ),'sameForShippingGroup',array('HtmlTag', array('tag' => 'div')));

//        $sameForShipping = $this->getDisplayGroup('sameForShippingGroup');
//        $sameForShipping->setDecorators(array(
//            'FormElements',
//            array('HtmlTag',array('tag'=>'p', 'class' => 'mobile-desktop-phone-block'))
//        ));
    }

    private function _setRequired(array $elements) {
        array_walk($elements, function($element) {
            $element->setRequired(true)
                ->setAttribs(array('class' => 'quote-required required'));
        });
    }
}
