<?php

class Quote_Forms_Address extends Forms_Address_Abstract {

	public function init() {
		parent::init();

		$this->setLegend('Billing address')
			->setAttribs(array(
				'id'     => 'quote-user-address',
				'class'  => 'toaster-checkout _fajax',
				'method' => Zend_Form::METHOD_POST
			));

		$this->setDecorators(array('FormElements', 'Form'));

		// setting required fields
		$this->getElement('firstname')->setRequired(true)->setAttrib('class', 'required');
		$this->getElement('email')->setRequired(true)->setAttrib('class', 'required');

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
				'mobile'
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
			'name'   => 'quoteMe',
			'id'     => 'quote-me',
			'label'  => 'Quote me!',
			'ignore' => true
		)));
	}

}
