<?php

class Quote_Forms_Quote extends Forms_Address_Abstract {

	public function init() {
		parent::init();

		$this->setLegend('Billing address')
			->setAttribs(array(
				'id'     => 'billing-user-address',
				'class'  => 'toaster-quote',
				'method' => Zend_Form::METHOD_POST
			));

		$this->setDecorators(array('FormElements', 'Form'));

		// setting required fields
		$this->getElement('firstname')->setRequired(true)->setAttrib('class', 'required');
		$this->getElement('email')->setRequired(true)->setAttrib('class', 'required');

		$this->addElement(new Zend_Form_Element_Checkbox(array(
			'name'  => 'sameForShipping',
			'id'    => 'same-for-shipping',
			'label' => 'Use same data for shipping?',
		)));

		$this->addDisplayGroups(array(
			'lcol' => array(
				'firstname',
				'lastname',
				'company',
				'email',
				'address1',
				'address2'
			),
			'rcol' => array(
				'country',
				'city',
				'state',
				'zip',
				'phone',
				'mobile',
				'sameForShipping'
			)
		));

		$lcol = $this->getDisplayGroup('lcol')
			->setDecorators(array(
				'FormElements',
			    'Fieldset',
		));

		$rcol = $this->getDisplayGroup('rcol')
			->setDecorators(array(
				'FormElements',
			    'Fieldset',
		));

		$this->setElementDecorators(array(
			'ViewHelper',
			'Label',
			array('HtmlTag', array('tag' => 'div'))
		));

		$this->addElement(new Zend_Form_Element_Submit(array(
			'name'   => 'sendQuote',
			'id'     => 'send-quote',
			'label'  => 'Send me a quote',
			'ignore' => true
		)));
	}

}
