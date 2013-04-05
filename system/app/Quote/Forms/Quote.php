<?php

class Quote_Forms_Quote extends Forms_Address_Abstract {

	public function init() {
		parent::init();

        //initial params and attributes
        $this->setLegend('Billing address')
			->setAttribs(array(
				'id'     => 'plugin-quote-quoteform',
				'class'  => 'toaster-quote',
				'method' => Zend_Form::METHOD_POST
			)
        );

        //only neccesarry decorators
		$this->setDecorators(array('FormElements', 'Form'));

		// setting required fields
		$this->getElement('firstname')->setRequired(true)->setAttrib('class', 'quote-required');
		$this->getElement('email')->setRequired(true)->setAttrib('class', 'quote-required');
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

		//adding display groups
        $this->addDisplayGroups(array(
			'leftColumn'  => array('firstname', 'lastname', 'company', 'email', 'address1', 'address2'),
			'rightColumn' => array('country', 'city', 'state', 'zip', 'phone', 'disclaimer', 'sameForShipping')
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

    private function _applyDecorators() {
        $this->setElementDecorators(array(
            'ViewHelper',
            'Label',
            array('HtmlTag', array('tag' => 'div'))
        ));
    }

}
