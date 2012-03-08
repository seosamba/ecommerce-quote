<?php

class Quote_Forms_Address extends Forms_Address_Abstract {

	public function init() {
		parent::init();

		$this->setAttribs(array(
			'class' => '_fajax'
		));

		$this->getElement('firstname')->setRequired(true)
			->setLabel('First Name *');
		$this->getElement('email')->setRequired(true)
			->setLabel('E-mail *');

		$this->addElement(new Zend_Form_Element_Submit(array(
			'name'   => 'quoteMe',
			'id'     => 'quote-me',
			'label'  => 'Quote me!',
			'ignore' => true
		)));
	}

}
